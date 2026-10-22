<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Clients\KorapayClient;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Console\Command;

class AttachKorapayReferenceToOrder extends Command
{
    protected $signature = 'payments:attach-korapay
        {order_number : Order number to attach to (e.g. DS-XXXX)}
        {reference : Korapay reference (e.g. KPY-... or krp_...)}
        {--dry-run : Validate only, do not write anything}';

    protected $description = 'Attach an orphan Korapay reference to an order and mark it paid (safe recovery for redirect/legacy flows)';

    public function handle(
        KorapayClient $client,
        PaymentService $paymentService,
        CurrencyConversionService $conversionService,
    ): int {
        $orderNumber = (string) $this->argument('order_number');
        $reference = (string) $this->argument('reference');
        $dryRun = (bool) $this->option('dry-run');

        /** @var Order|null $order */
        $order = Order::query()->where('number', $orderNumber)->first();
        if (! $order) {
            $this->error('Order not found: ' . $orderNumber);
            return self::FAILURE;
        }

        // Verify with Korapay first (source of truth).
        try {
            $resp = $client->verify($reference);
        } catch (\Throwable $e) {
            $this->error('Korapay verify failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $data = is_array($resp->data) ? $resp->data : [];
        $status = strtolower((string) ($data['status'] ?? ''));
        $amount = isset($data['amount_paid']) ? (float) $data['amount_paid'] : (isset($data['amount']) ? (float) $data['amount'] : 0.0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        $this->line('Korapay verify: ' . json_encode([
            'status' => $status,
            'reference' => $data['reference'] ?? $reference,
            'amount_paid' => $data['amount_paid'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $currency,
        ], JSON_UNESCAPED_SLASHES));

        if (! in_array($status, ['success', 'paid', 'captured', 'succeeded'], true)) {
            $this->error('Korapay status is not paid/success; refusing to mark order paid.');
            return self::FAILURE;
        }

        if ($amount <= 0 || $currency === '') {
            $this->error('Korapay verify did not return a valid amount/currency.');
            return self::FAILURE;
        }

        // Safety: validate amount matches the order total in the provider currency.
        $orderCurrency = strtoupper((string) ($order->currency ?? 'USD'));
        $orderTotal = (float) ($order->grand_total ?? 0);
        if ($orderTotal <= 0) {
            $this->error('Order total is invalid; refusing to attach payment.');
            return self::FAILURE;
        }

        $expected = $orderCurrency === $currency
            ? $orderTotal
            : (float) $conversionService->convertAmount($orderTotal, $orderCurrency, $currency);

        // XOF has 0 decimals; keep tight tolerance for integer currencies, loose for 2dp.
        $allowedDelta = $currency === 'XOF' ? 1.0 : 0.01;

        if (abs($amount - $expected) > $allowedDelta) {
            $this->error(sprintf(
                'Amount mismatch. Korapay=%s %s, expected≈%s %s (delta=%s, allowed=%s).',
                $amount,
                $currency,
                $expected,
                $currency,
                abs($amount - $expected),
                $allowedDelta
            ));
            return self::FAILURE;
        }

        // Ensure reference is not already linked to a different order.
        $existing = Payment::query()
            ->where('provider', 'korapay')
            ->where('provider_reference', $reference)
            ->latest('id')
            ->first();

        if ($existing && (int) $existing->order_id !== (int) $order->id) {
            $this->error(sprintf(
                'Reference already linked to a different order (payment_id=%d order_id=%d). Refusing.',
                (int) $existing->id,
                (int) $existing->order_id
            ));
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('DRY-RUN OK: would attach and mark as paid.');
            return self::SUCCESS;
        }

        $payment = $existing ?: Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'pending',
            'provider_reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'paid_at' => null,
            'meta' => [
                'recovery' => [
                    'at' => now()->toISOString(),
                    'tool' => 'payments:attach-korapay',
                    'order_number' => $order->number,
                ],
                'korapay_verify' => $data,
                'korapay_verify_raw' => $resp->raw,
            ],
        ]);

        // Make sure totals match the provider-facing values we just verified.
        $payment->forceFill(['amount' => $amount, 'currency' => $currency])->save();

        $paymentService->markAsPaid($payment);

        $this->info(sprintf('Attached and marked paid: payment_id=%d order=%s reference=%s', (int) $payment->id, (string) $order->number, $reference));
        return self::SUCCESS;
    }
}

