<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Models\AffiliateWithdrawal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AffiliateOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $pendingCommissions = AffiliateCommission::query()
            ->where('status', 'pending')
            ->count();

        $approvedCommissions = AffiliateCommission::query()
            ->where('status', 'approved')
            ->count();

        $totalPaidCommissions = AffiliateCommission::query()
            ->where('status', 'paid')
            ->sum('commission_amount');

        $pendingWithdrawals = AffiliateWithdrawal::query()
            ->where('status', 'pending')
            ->count();

        $activeAffiliates = Affiliate::query()
            ->where('status', '!=', 'suspended')
            ->count();

        return [
            Stat::make('Active affiliates', (string) $activeAffiliates)
                ->description('Ready to promote')
                ->color('primary')
                ->url(\App\Filament\Resources\AffiliateResource::getUrl()),
            Stat::make('Pending commissions', (string) $pendingCommissions)
                ->description('Awaiting approval')
                ->color('warning')
                ->url(\App\Filament\Resources\AffiliateCommissionResource::getUrl()),
            Stat::make('Approved commissions', (string) $approvedCommissions)
                ->description('Awaiting payout')
                ->color('success')
                ->url(\App\Filament\Resources\AffiliateCommissionResource::getUrl()),
            Stat::make('Paid commissions', '$' . number_format($totalPaidCommissions, 2))
                ->description('Total paid out')
                ->color('success')
                ->url(\App\Filament\Resources\AffiliateCommissionResource::getUrl()),
            Stat::make('Pending withdrawals', (string) $pendingWithdrawals)
                ->description('Need processing')
                ->color('danger')
                ->url(\App\Filament\Resources\AffiliateWithdrawalResource::getUrl()),
        ];
    }
}
