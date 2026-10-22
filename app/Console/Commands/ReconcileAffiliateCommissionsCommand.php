<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Services\AffiliateCommissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ReconcileAffiliateCommissionsCommand extends Command
{
    protected $signature = 'affiliate:reconcile-commissions {--days= : Approve commissions older than X days}';

    protected $description = 'Approve pending affiliate commissions that have aged for the configured number of days.';

    public function handle(AffiliateCommissionService $service): int
    {
        $days = $this->option('days') ?? Config::get('affiliate.auto_approve_days', 7);
        $cutoff = now()->subDays((int) $days);

        $commissions = AffiliateCommission::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($commissions->isEmpty()) {
            $this->info('No old pending commissions found.');

            return self::SUCCESS;
        }

        foreach ($commissions as $commission) {
            $service->approveCommission($commission);
        }

        $this->info(sprintf('Approved %d commission(s) older than %d day(s).', $commissions->count(), $days));

        return self::SUCCESS;
    }
}
