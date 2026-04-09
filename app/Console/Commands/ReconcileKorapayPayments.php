<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentService;
use Illuminate\Console\Command;

class ReconcileKorapayPayments extends Command
{
    protected $signature = 'payments:reconcile-korapay
        {--limit=50 : Max payments to verify per run}
        {--min-age=3 : Only verify payments older than N minutes}
        {--max-age=4320 : Only verify payments newer than N minutes (default 3 days)}
        {--dry-run : Show which references would be verified without calling Korapay}';

    protected $description = 'Verify pending Korapay payments so successful payments do not rely on browser/app redirects';

    public function handle(PaymentService $paymentService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $minAgeMinutes = max(0, (int) $this->option('min-age'));
        $maxAgeMinutes = max(1, (int) $this->option('max-age'));
        $dryRun = (bool) $this->option('dry-run');

        $minAge = now()->subMinutes($minAgeMinutes);
        $maxAge = now()->subMinutes($maxAgeMinutes);

        $query = Payment::query()
            ->where('provider', 'korapay')
            ->whereNotIn('status', ['paid'])
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', $minAge)
            ->where('created_at', '>=', $maxAge)
            ->orderBy('id')
            ->limit($limit);

        $payments = $query->get(['id', 'provider_reference', 'status', 'created_at']);

        if ($payments->isEmpty()) {
            $this->line('No pending Korapay payments to reconcile.');
            return self::SUCCESS;
        }

        $verified = 0;
        $paid = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            $reference = (string) $payment->provider_reference;
            if ($reference === '') {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '[DRY-RUN] payment_id=%d reference=%s status=%s created_at=%s',
                    (int) $payment->id,
                    $reference,
                    (string) $payment->status,
                    (string) $payment->created_at
                ));
                continue;
            }

            try {
                $verified++;
                $result = $paymentService->verifyKorapay($reference);
                if ($result->status === 'paid') {
                    $paid++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf('Failed verify reference=%s: %s', $reference, $e->getMessage()));
            }
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Scanned', $payments->count()],
            ['Verified', $verified],
            ['Marked paid', $paid],
            ['Failures', $failed],
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

