<?php

namespace App\Domain\Affiliates\Services;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AffiliateCommissionService
{
    public function createCommission(Order $order, OrderItem $item, Affiliate $affiliate): AffiliateCommission
    {
        return DB::transaction(function () use ($order, $item, $affiliate) {
            $baseAmount = $item->price;
            $commissionRate = $affiliate->commission_rate ?? config('affiliate.default_commission_rate', 0.10);
            $commissionAmount = $baseAmount * $commissionRate;

            return AffiliateCommission::create([
                'affiliate_id' => $affiliate->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'status' => 'pending',
            ]);
        });
    }

    public function approveCommission(AffiliateCommission $commission): void
    {
        if (in_array($commission->status, ['approved', 'paid'], true)) {
            return;
        }

        DB::transaction(function () use ($commission) {
            $commission->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $this->adjustAffiliateBalances($commission->affiliate, $commission->commission_amount, +1);
        });
    }

    public function rejectCommission(AffiliateCommission $commission, string $reason = null): void
    {
        if ($commission->status === 'rejected') {
            return;
        }

        $commission->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);
    }

    public function markCommissionPaid(AffiliateCommission $commission): void
    {
        if ($commission->status === 'paid') {
            return;
        }

        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    private function adjustAffiliateBalances(Affiliate $affiliate, float $amount, int $direction): void
    {
        if ($direction === 1) {
            $affiliate->increment('balance_available', $amount);
            $affiliate->increment('total_earned', $amount);
        } elseif ($direction === -1) {
            $affiliate->decrement('balance_available', min($affiliate->balance_available, $amount));
        }
    }
}
