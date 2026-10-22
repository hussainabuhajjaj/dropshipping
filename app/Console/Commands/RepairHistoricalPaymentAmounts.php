<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Models\Payment;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Console\Command;

class RepairHistoricalPaymentAmounts extends Command
{
    protected $signature = 'payments:repair-historical-amounts
        {--provider=korapay : Payment provider to target}
        {--status=paid,success,succeeded,captured : Comma-separated statuses to include}
        {--order-number= : Only repair a specific order number}
        {--limit=0 : Max payments to scan (0 = all)}
        {--dry-run : Preview changes without writing}';

    protected $description = 'Backfill/repair historical payment amount and currency consistency using order totals';

    public function handle(CurrencyConversionService $conversionService): int
    {
        $provider = (string) $this->option('provider');
        $orderNumber = (string) ($this->option('order-number') ?? '');
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $statuses = collect(explode(',', (string) $this->option('status')))
            ->map(fn (string $value) => strtolower(trim($value)))
            ->filter()
            ->values()
            ->all();

        if (empty($statuses)) {
            $this->error('No statuses were provided.');
            return self::FAILURE;
        }

        $query = Payment::query()
            ->with('order')
            ->where('provider', $provider)
            ->whereIn('status', $statuses)
            ->orderBy('id');

        if ($orderNumber !== '') {
            $query->whereHas('order', fn ($q) => $q->where('number', $orderNumber));
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $payments = $query->get();

        if ($payments->isEmpty()) {
            $this->warn('No matching payments found.');
            return self::SUCCESS;
        }

        $scanned = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($payments as $payment) {
            $scanned++;

            $order = $payment->order;
            if (! $order) {
                $skipped++;
                continue;
            }

            $orderCurrency = strtoupper((string) ($order->currency ?? 'USD'));
            $paymentCurrency = strtoupper((string) ($payment->currency ?: $orderCurrency));
            $orderTotal = (float) ($order->grand_total ?? 0);

            if ($orderTotal <= 0) {
                $skipped++;
                continue;
            }

            try {
                $expectedAmount = $this->expectedAmount(
                    $conversionService,
                    $orderTotal,
                    $orderCurrency,
                    $paymentCurrency
                );

                $currentAmount = is_numeric($payment->amount) ? (float) $payment->amount : null;
                $needsAmountRepair = $currentAmount === null
                    || $currentAmount <= 0
                    || abs($currentAmount - $expectedAmount) > $this->allowedDelta($expectedAmount);

                $needsCurrencyRepair = empty($payment->currency);

                if (! $needsAmountRepair && ! $needsCurrencyRepair) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[DRY-RUN] payment_id=%d order=%s amount %s -> %.2f, currency %s -> %s',
                        $payment->id,
                        (string) $order->number,
                        $currentAmount === null ? 'null' : number_format($currentAmount, 2, '.', ''),
                        $expectedAmount,
                        (string) ($payment->currency ?? 'null'),
                        $paymentCurrency
                    ));
                    $updated++;
                    continue;
                }

                $meta = is_array($payment->meta) ? $payment->meta : [];
                $meta['repair'] = [
                    'at' => now()->toISOString(),
                    'tool' => 'payments:repair-historical-amounts',
                    'reason' => 'historical_amount_currency_backfill',
                    'old_amount' => $currentAmount,
                    'new_amount' => $expectedAmount,
                    'old_currency' => $payment->currency,
                    'new_currency' => $paymentCurrency,
                    'order_total' => $orderTotal,
                    'order_currency' => $orderCurrency,
                ];

                $payment->update([
                    'amount' => $expectedAmount,
                    'currency' => $paymentCurrency,
                    'meta' => $meta,
                ]);
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
                $this->error(sprintf(
                    'Failed payment_id=%d order=%s: %s',
                    $payment->id,
                    (string) $order->number,
                    $e->getMessage()
                ));
            }
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Scanned', $scanned],
            ['Updated', $updated],
            ['Skipped', $skipped],
            ['Errors', $errors],
            ['Mode', $dryRun ? 'dry-run' : 'write'],
        ]);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function expectedAmount(
        CurrencyConversionService $conversionService,
        float $orderTotal,
        string $orderCurrency,
        string $paymentCurrency
    ): float {
        if ($orderCurrency === $paymentCurrency) {
            return round($orderTotal, 2);
        }

        $converted = $conversionService->convertAmount($orderTotal, $orderCurrency, $paymentCurrency);
        return round((float) $converted, 2);
    }

    private function allowedDelta(float $expected): float
    {
        return max(5.0, $expected * 0.05);
    }
}

