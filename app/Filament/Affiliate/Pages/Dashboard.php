<?php

namespace App\Filament\Affiliate\Pages;

use App\Domain\Affiliates\Models\Affiliate;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected string $view = 'filament.affiliate.pages.dashboard';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    public function getViewData(): array
    {
        /** @var Affiliate|null $affiliate */
        $affiliate = Filament::auth()->user();

        if (! $affiliate) {
            return [];
        }

        $commissionBase = $affiliate->commissions();
        $withdrawalBase = $affiliate->withdrawals();

        $stats = [
            'total_commissions' => (clone $commissionBase)->sum('commission_amount'),
            'pending_commissions' => (clone $commissionBase)->where('status', 'pending')->count(),
            'approved_commissions' => (clone $commissionBase)->where('status', 'approved')->count(),
            'total_withdrawn' => (clone $withdrawalBase)->where('status', 'processed')->sum('amount'),
            'pending_withdrawals' => (clone $withdrawalBase)->where('status', 'pending')->count(),
            'referral_count' => $affiliate->referrals()->count(),
            'converted_referrals' => $affiliate->referrals()->whereNotNull('user_id')->count(),
        ];

        return [
            'available_balance' => $affiliate->balance_available,
            'pending_balance' => $affiliate->balance_pending,
            'total_earned' => $affiliate->total_earned,
            'referral_code' => $affiliate->referral_code,
            'referral_link' => url('/?ref=' . $affiliate->referral_code),
            'stats' => $stats,
            'recent_commissions' => $affiliate->commissions()->latest()->limit(5)->get(),
            'recent_withdrawals' => $affiliate->withdrawals()->latest()->limit(5)->get(),
            'referral_details' => $affiliate->referrals()->latest()->limit(5)->get(),
            'affiliate_name' => $affiliate->name,
        ];
    }
}
