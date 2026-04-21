<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhook;
use App\Domain\Observability\EventLogger;
use App\Events\Orders\OrderPaid;
use App\Infrastructure\Payments\Clients\KorapayClient;
use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly EventLogger $logger,
    )
    {
    }

    /**
     * Handle incoming webhook in an idempotent way and update payment + order payment_status.
     */
    public function handleWebhook(string $provider, string $eventId, array $payload): Payment
    {
        return DB::transaction(function () use ($provider, $eventId, $payload) {
            $this->assertPayloadHasBasics($payload, $provider);

            $webhook = PaymentWebhook::firstOrCreate(
                ['external_event_id' => $eventId],
                [
                    'provider' => $provider,
                    'payload' => $payload,
                    'processed_at' => null,
                ]
            );

            // If already processed, short-circuit to prevent double confirmation
            if ($webhook->processed_at) {
                return $webhook->payment ?? $this->resolvePaymentFromPayload($provider, $payload);
            }

            $payment = $this->resolvePaymentFromPayload($provider, $payload);

            $this->applyStatusFromPayload($payment, $payload);

            $this->logger->payment($payment, 'webhook', strtolower($payload['status'] ?? 'pending'), null, $payload);

            $webhook->payment()->associate($payment);
            $webhook->processed_at = now();
            $webhook->save();

            return $payment;
        });
    }

    /**
     * Confirm a payment and update order payment status without altering fulfillment status.
     */
    public function markAsPaid(Payment $payment): Payment
    {
        $wasPaid = $payment->status === 'paid';

        if (! $wasPaid) {
            $payment->fill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();
        }

        $order = $payment->order()->first();

        if ($order) {
            $order->payment_status = 'paid';
            if ($order->status === 'pending') {
                $order->status = 'paid';
            }
            $order->save();

            if (! $wasPaid) {
                event(new OrderPaid($order));
            }
        }

        $this->logger->payment($payment, 'payment', 'paid', 'Payment marked as paid');

        return $payment->refresh();
    }

    private function resolvePaymentFromPayload(string $provider, array $payload): Payment
    {
        $providerReference = $payload['provider_reference'] ?? $payload['transaction_id'] ?? null;
        $orderNumber = $payload['order_number'] ?? null;
        $amount = $payload['amount'] ?? null;
        $currency = $payload['currency'] ?? null;
        $idempotencyKey = $payload['idempotency_key'] ?? $payload['event_id'] ?? null;

        $this->assertPayloadHasBasics($payload, $provider);

        // Some Korapay events (and some verify responses) can omit merchant metadata.
        // In that case, resolve by provider reference and update the existing payment.
        if (($orderNumber === null || $orderNumber === '') && $provider === 'korapay') {
            if (! $providerReference) {
                throw new RuntimeException('Provider reference missing in Korapay payload');
            }

            $existing = Payment::query()
                ->where('provider', $provider)
                ->where('provider_reference', $providerReference)
                ->latest('id')
                ->first();

            if (! $existing) {
                throw new RuntimeException('Order number missing in Korapay payload and no payment found for reference');
            }

            $this->assertPaymentTotalsMatch($existing, $amount, $currency);
            return $existing;
        }

        /** @var Order $order */
        $order = Order::where('number', $orderNumber)->firstOrFail();

        $method = $this->extractPaymentMethodFromPayload($payload);

        /** @var Payment|null $existing */
        $existing = Payment::query()
            ->where('provider', $provider)
            ->where('provider_reference', $providerReference)
            ->latest('id')
            ->first();

        if ($existing) {
            $this->assertPaymentTotalsMatch($existing, $amount, $currency);
        } elseif ($method === 'mobile_money') {
            $expected = $this->resolveChargeForKorapay($order, $method);
            $this->assertNumericTotalsMatch($expected['amount'], $expected['currency'], $amount, $currency);
        } else {
            $this->assertTotalsMatch($order, $amount, $currency);
        }

        $payment = Payment::firstOrCreate(
            [
                'provider' => $provider,
                'provider_reference' => $providerReference,
            ],
            [
                'order_id' => $order->id,
                'status' => 'pending',
                'amount' => $amount ?? $order->grand_total,
                'currency' => $currency ?? $order->currency,
                'meta' => $payload,
                'idempotency_key' => $idempotencyKey,
            ]
        );

        // keep idempotency key synced even if payment existed
        if ($idempotencyKey && $payment->idempotency_key !== $idempotencyKey) {
            $payment->forceFill(['idempotency_key' => $idempotencyKey])->save();
        }

        return $payment;
    }

    private function applyStatusFromPayload(Payment $payment, array $payload): void
    {
        $status = strtolower($payload['status'] ?? 'pending');

        if (in_array($status, ['paid', 'captured', 'success', 'succeeded'], true)) {
            $this->markAsPaid($payment);
            return;
        }

        if (in_array($status, ['failed', 'declined'], true)) {
            $payment->update(['status' => 'failed']);
            Log::warning('Payment failed', ['payment_id' => $payment->id, 'payload' => $payload]);
            return;
        }

        if ($status === 'authorized') {
            $payment->update(['status' => 'authorized']);
        }
    }

    private function assertPayloadHasBasics(array $payload, ?string $provider = null): void
    {
        if (! isset($payload['amount']) || ! is_numeric($payload['amount'])) {
            throw new RuntimeException('Amount missing or invalid in webhook payload');
        }

        if (empty($payload['currency'])) {
            throw new RuntimeException('Currency missing in webhook payload');
        }

        if (! empty($payload['order_number'])) {
            return;
        }

        // Korapay can send some events without merchant metadata. If we have a reference, we can still resolve.
        if ($provider === 'korapay' && ! empty($payload['provider_reference'] ?? null)) {
            return;
        }

        throw new RuntimeException('Order number missing in webhook payload');
    }

    private function assertTotalsMatch(Order $order, float|string|null $amount, ?string $currency): void
    {
        $numericAmount = (float) $amount;
        if ($numericAmount <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        if (strcasecmp((string) $currency, $order->currency) !== 0) {
            throw new InvalidArgumentException('Currency mismatch for order.');
        }

        if (abs($numericAmount - (float) $order->grand_total) > 0.01) {
            throw new InvalidArgumentException('Amount does not match order total.');
        }
    }

    private function assertPaymentTotalsMatch(Payment $payment, float|string|null $amount, ?string $currency): void
    {
        $expectedAmount = (int) ($payment->amount ?? 0);
        $expectedCurrency = (string) ($payment->currency ?? '');

        if ($expectedAmount <= 0 || $expectedCurrency === '') {
            // If payment is missing charged totals, fall back to order-level checks elsewhere.
            return;
        }

        $paystackAmount = (int) $amount;

        if ((int) $paystackAmount !== (int) $payment->amount) {
            throw new InvalidArgumentException('Payment amount mismatch');
        }

        $this->assertNumericTotalsMatch($expectedAmount, $expectedCurrency, $amount, $currency);
    }

    private function assertNumericTotalsMatch(float $expectedAmount, string $expectedCurrency, float|string|null $amount, ?string $currency): void
    {
        $numericAmount = (float) $amount;
        if ($numericAmount <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        if (strcasecmp((string) $currency, $expectedCurrency) !== 0) {
            throw new InvalidArgumentException('Currency mismatch for payment.');
        }

        // Compare loosely for minor rounding differences; XOF is integer, USD is 2 decimals.
        if (abs($numericAmount - $expectedAmount) > 0.01) {
            throw new InvalidArgumentException('Amount does not match expected charged total.');
        }
    }

    private function extractPaymentMethodFromPayload(array $payload): ?string
    {
        $method = data_get($payload, 'payment_method')
            ?? data_get($payload, 'metadata.payment_method')
            ?? data_get($payload, 'korapay.data.metadata.payment_method')
            ?? data_get($payload, 'korapay.metadata.payment_method');

        $method = is_string($method) ? strtolower(trim($method)) : null;

        return $method !== '' ? $method : null;
    }

    /**
     * Resolve the exact amount/currency we should charge Korapay for this order + method.
     *
     * @return array{amount: float, currency: string, fx_rate_used: float|null}
     */
    private function resolveChargeForKorapay(Order $order, string $method): array
    {
        $baseCurrency = (string) ($order->currency ?? config('currency.base', 'USD'));
        $baseAmount = (float) ($order->grand_total ?? 0);

        if ($method !== 'mobile_money') {
            return [
                'amount' => $baseAmount,
                'currency' => $baseCurrency,
                'fx_rate_used' => null,
            ];
        }

        $chargeCurrency = 'XOF';
        $converter = app(CurrencyConversionService::class);

        // Use the configured FX rate (FX_USD_XOF) and currency decimals (XOF => 0)
        $chargeAmount = (float) $converter->convertAmount($baseAmount, $baseCurrency, $chargeCurrency);
        $fxRate = null;
        try {
            $fxRate = $converter->rate($converter->normalize($baseCurrency), $converter->normalize($chargeCurrency));
        } catch (\Throwable) {
            // Leave as null; conversion already validated rate existence.
        }

        return [
            'amount' => $chargeAmount,
            'currency' => $chargeCurrency,
            'fx_rate_used' => $fxRate,
        ];
    }


    public function initializeKorapay(
        Order $order,
        Payment $payment,
        array $customer = [],
        string $method = 'card',
        ?string $returnUrl = null
    ): array
    {
        $client = app(KorapayClient::class);

        if (! $payment->provider_reference) {
            $payment->update(['provider_reference' => $this->buildKorapayReference($order)]);
        }

        $charge = $this->resolveChargeForKorapay($order, $method);
        $chargedAmount = $charge['amount'];
        $chargedCurrency = $charge['currency'];

        // Persist provider-facing charged totals for reconciliation and webhook validation.
        $existingMeta = is_array($payment->meta) ? $payment->meta : [];
        $payment->forceFill([
            'amount' => $chargedAmount,
            'currency' => $chargedCurrency,
            'meta' => array_merge($existingMeta, [
                'order_amount' => (float) ($order->grand_total ?? 0),
                'order_currency' => (string) ($order->currency ?? config('currency.base', 'USD')),
                'fx_rate_used' => $charge['fx_rate_used'],
                'charged_amount' => $chargedAmount,
                'charged_currency' => $chargedCurrency,
            ]),
        ])->save();

        $payload = [
            'amount' => $chargedAmount,
            'currency' => $chargedCurrency,
            'reference' => $payment->provider_reference,
            'redirect_url' => $returnUrl ?: url('/api/mobile/v1/payments/redirect'),
            'customer' => [
                'email' => $customer['email'] ?? $order->email,
                'name' => $customer['name'] ?? $order->guest_name ?? $order->customer?->name,
            ],
            'channels' => [$method],
            'default_channel' => $method,
            'metadata' => [
                'order_number' => $order->number,
                'payment_id' => $payment->id,
                'customer_id' => $order->customer_id,
                'payment_method' => $method,
            ],
        ];

        $response = $client->initialize($payload);
        $data = is_array($response->data) ? $response->data : [];

        $payment->update([
            'meta' => array_merge($payment->meta ?? [], ['korapay_init' => $data]),
        ]);

        return [
            'reference' => $data['reference'] ?? $payment->provider_reference,
            'checkout_url' => $data['authorization_url'] ?? $data['checkout_url'] ?? null,
        ];
    }

    public function verifyKorapay(string $reference): Payment
    {
        $client = app(KorapayClient::class);
        $response = $client->verify($reference);
        $data = is_array($response->data) ? $response->data : [];

        // Persist the raw verify response for debugging/reconciliation even when redirect fails.
        // This helps diagnose "dashboard says success but API says pending" cases.
        $existingForRef = Payment::query()
            ->where('provider', 'korapay')
            ->where('provider_reference', $reference)
            ->latest('id')
            ->first();

        if ($existingForRef) {
            $existingMeta = is_array($existingForRef->meta) ? $existingForRef->meta : [];
            $existingForRef->update([
                'meta' => array_merge($existingMeta, [
                    'korapay_verify' => $data,
                    'korapay_verify_raw' => $response->raw,
                    'korapay_verify_at' => now()->toISOString(),
                ]),
            ]);
        }

        $payload = $this->normalizeKorapayPayload($data, $reference);
        $eventId = $payload['event_id'] ?? ('verify:' . $reference);

        // Korapay verify payload can omit order_number metadata.
        // In that case, resolve by provider reference and update status directly.
        if (empty($payload['order_number'])) {
            $payment = Payment::query()
                ->where('provider', 'korapay')
                ->where('provider_reference', $reference)
                ->latest('id')
                ->first();

            if ($payment) {
                $existingMeta = is_array($payment->meta) ? $payment->meta : [];
                $payment->update([
                    'meta' => array_merge($existingMeta, ['korapay_verify' => $data]),
                ]);

                $this->applyStatusFromPayload($payment, $payload);

                return $payment->refresh();
            }
        }

        return $this->handleWebhook('korapay', $eventId, $payload);
    }

    private function normalizeKorapayPayload(array $data, string $reference): array
    {
        return [
            'event_id' => $data['id'] ?? $data['event_id'] ?? $reference,
            'provider_reference' => $data['reference'] ?? $reference,
            'transaction_id' => $data['id'] ?? null,
            'order_number' => $data['metadata']['order_number']
                ?? $data['meta']['order_number']
                ?? $data['order_number']
                ?? null,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? null,
            'korapay' => $data,
        ];
    }

    private function buildKorapayReference(Order $order): string
    {
        return 'krp_' . strtolower($order->number) . '_' . strtolower(Str::random(6));
    }

    public function initializePaystack(
        Order $order,
        Payment $payment,
        array $customer = [],
        string $method = 'card',
        ?string $returnUrl = null
    ): array
    {
        $paystackService = app(PaystackService::class);

        if (! $payment->provider_reference) {
            $newReference = $this->buildPaystackReference($order);
            Log::info('Generating new Paystack reference', [
                'order_number' => $order->number,
                'new_reference' => $newReference,
            ]);
            $payment->update(['provider_reference' => $newReference]);
        } else {
            Log::info('Using existing Paystack reference', [
                'order_number' => $order->number,
                'existing_reference' => $payment->provider_reference,
            ]);
        }

        // Use ONLY the payment amount as provided from frontend
        $amount = (int) $payment->amount;
        $currency = (string) ($payment->currency ?? 'XOF');

        // Add mandatory debug logging
        Log::info('PaymentService initializePaystack', [
            'frontend_amount' => $amount,
            'payment_amount' => $payment->amount,
            'order_amount' => $order->grand_total,
            'order_currency' => $order->currency,
            'payment_currency' => $payment->currency,
            'payment_method' => $method,
            'customer' => $customer,
        ]);

        // Persist provider-facing charged totals for reconciliation and webhook validation
        $existingMeta = is_array($payment->meta) ? $payment->meta : [];
        $payment->forceFill([
            'amount' => $amount,
            'currency' => $currency,
            'meta' => array_merge($existingMeta, [
                'order_amount' => (float) ($order->grand_total ?? 0),
                'order_currency' => (string) ($order->currency ?? config('currency.base', 'USD')),
                'charged_amount' => $amount,
                'charged_currency' => $currency,
            ]),
        ])->save();

        try {
            Log::info('Calling Paystack service initialize', [
                'amount' => $amount,
                'currency' => $currency,
                'payment_reference' => $payment->provider_reference,
            ]);

            $response = $paystackService->initialize($order, $payment, $customer, $method);
            $data = is_array($response->data) ? $response->data : [];

            Log::info('Paystack service initialize response', [
                'response_status' => $response->status,
                'response_data_keys' => array_keys($data),
            ]);

            $payment->update([
                'meta' => array_merge($payment->meta ?? [], ['paystack_init' => $data]),
            ]);

            return [
                'reference' => $data['reference'] ?? $payment->provider_reference,
                'authorization_url' => $data['authorization_url'] ?? null,
                'checkout_url' => $data['authorization_url'] ?? null,
                'access_code' => $data['access_code'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'display_text' => $data['display_text'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Paystack service initialize failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'amount' => $amount,
                'currency' => $currency,
                'payment_reference' => $payment->provider_reference,
            ]);

            throw $e;
        }
    }

    public function verifyPaystack(string $reference): Payment
    {
        $paystackService = app(PaystackService::class);
        $response = $paystackService->verify($reference);
        $data = is_array($response->data) ? $response->data : [];

        $existingForRef = Payment::query()
            ->where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->latest('id')
            ->first();

        if ($existingForRef) {
            $existingMeta = is_array($existingForRef->meta) ? $existingForRef->meta : [];
            $existingForRef->update([
                'meta' => array_merge($existingMeta, [
                    'paystack_verify' => $data,
                    'paystack_verify_raw' => $response->raw ?? null,
                    'paystack_verify_at' => now()->toISOString(),
                ]),
            ]);
        }

        $payload = $this->normalizePaystackPayload($data, $reference);
        $eventId = $payload['event_id'] ?? ('verify:' . $reference);

        if (empty($payload['order_number'])) {
            $payment = Payment::query()
                ->where('provider', 'paystack')
                ->where('provider_reference', $reference)
                ->latest('id')
                ->first();

            if ($payment) {
                $existingMeta = is_array($payment->meta) ? $payment->meta : [];
                $payment->update([
                    'meta' => array_merge($existingMeta, ['paystack_verify' => $data]),
                ]);

                $this->applyStatusFromPayload($payment, $payload);

                return $payment->refresh();
            }
        }

        return $this->handleWebhook('paystack', $eventId, $payload);
    }

    private function normalizePaystackPayload(array $data, string $reference): array
    {
        $currency = (string) ($data['currency'] ?? '');
        $amount = isset($data['amount'])
            ? $this->normalizePaystackAmount((float) $data['amount'], $currency)
            : null;

        return [
            'event_id' => $data['id'] ?? $data['event_id'] ?? $reference,
            'provider_reference' => $data['reference'] ?? $reference,
            'transaction_id' => $data['id'] ?? null,
            'order_number' => $data['metadata']['order_number'] ?? null,
            'amount' => $amount,
            'currency' => $currency !== '' ? $currency : null,
            'status' => $data['status'] ?? null,
            'payment_method' => $data['channel'] ?? $data['metadata']['payment_method'] ?? null,
            'paystack' => $data,
        ];
    }

    private function buildPaystackReference(Order $order): string
    {
        return 'pstk_' . strtolower($order->number) . '_' . strtolower(Str::random(8)) . '_' . time();
    }

    /**
     * Resolve the exact amount/currency we should charge Paystack for this order + method.
     *
     * @return array{amount: float, currency: string, fx_rate_used: float|null}
     */
    private function resolveChargeForPaystack(Order $order, string $method): array
    {
        $baseCurrency = (string) ($order->currency ?? config('currency.base', 'USD'));
        $baseAmount = (float) ($order->grand_total ?? 0);

        // For card payments, use the order currency directly
        if ($method !== 'mobile_money') {
            return [
                'amount' => $baseAmount,
                'currency' => $baseCurrency,
                'fx_rate_used' => null,
            ];
        }

        // For mobile money, convert to supported local currencies
        $chargeCurrency = $this->getPaystackMobileMoneyCurrency($baseCurrency);
        $converter = app(CurrencyConversionService::class);

        if ($chargeCurrency !== $baseCurrency) {
            // Convert to the mobile money currency
            $chargeAmount = (float) $converter->convertAmount($baseAmount, $baseCurrency, $chargeCurrency);
            $fxRate = null;
            try {
                $fxRate = $converter->rate($converter->normalize($baseCurrency), $converter->normalize($chargeCurrency));
            } catch (\Throwable) {
                // Leave as null; conversion already validated rate existence.
            }

            return [
                'amount' => $chargeAmount,
                'currency' => $chargeCurrency,
                'fx_rate_used' => $fxRate,
            ];
        }

        return [
            'amount' => $baseAmount,
            'currency' => $baseCurrency,
            'fx_rate_used' => null,
        ];
    }

    /**
     * Get the appropriate mobile money currency for Paystack based on the base currency
     */
    private function getPaystackMobileMoneyCurrency(string $baseCurrency): string
    {
        return match (strtoupper($baseCurrency)) {
            'GHS' => 'GHS', // Ghana
            'KES' => 'KES', // Kenya
            'XOF' => 'XOF', // Cote d'Ivoire
            default => strtoupper($baseCurrency),
        };
    }

    private function normalizePaystackAmount(float $amount, string $currency): float
    {
        return $amount / 100;
    }
}
