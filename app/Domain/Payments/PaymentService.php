<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentWebhook;
use App\Domain\Observability\EventLogger;
use App\Events\Orders\OrderPaid;
use App\Infrastructure\Payments\Clients\KorapayClient;
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
            $this->assertPayloadHasBasics($payload);

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

        $this->assertPayloadHasBasics($payload);

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

    private function assertPayloadHasBasics(array $payload): void
    {
        if (empty($payload['order_number'])) {
            throw new RuntimeException('Order number missing in webhook payload');
        }

        if (! isset($payload['amount']) || ! is_numeric($payload['amount'])) {
            throw new RuntimeException('Amount missing or invalid in webhook payload');
        }

        if (empty($payload['currency'])) {
            throw new RuntimeException('Currency missing in webhook payload');
        }
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
        $expectedAmount = (float) ($payment->amount ?? 0);
        $expectedCurrency = (string) ($payment->currency ?? '');

        if ($expectedAmount <= 0 || $expectedCurrency === '') {
            // If payment is missing charged totals, fall back to order-level checks elsewhere.
            return;
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
}
