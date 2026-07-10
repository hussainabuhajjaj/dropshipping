<?php

declare(strict_types=1);

namespace App\Domain\Affiliates\Services;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Models\Coupon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function calculateCommissions(Order $order): Collection
    {
        $referral = $order->referral;
        if (! $referral || ! $referral->affiliate) {
            return collect();
        }

        $affiliate = $referral->affiliate;

        if ($affiliate->status === 'suspended') {
            return collect();
        }

        $coupon = null;
        if ($order->coupon_code) {
            $coupon = Coupon::query()->where('code', $order->coupon_code)->first();
        }

        $totalDiscountAmount = (float) $order->discount_total;
        $items = $order->orderItems;
        $itemCount = $items->count();

        $commissions = collect();

        foreach ($items as $item) {
            $baseAmount = (float) $item->price;
            $discountAmountApplied = 0.0;

            if ($coupon && $coupon->affects_affiliate_commission && $totalDiscountAmount > 0 && $itemCount > 0) {
                $itemWeight = $baseAmount / max($items->sum('price'), 0.01);
                $discountAmountApplied = round($totalDiscountAmount * $itemWeight, 2);
            }

            $commissionBaseAmount = round($baseAmount - $discountAmountApplied, 2);
            $commissionRate = $affiliate->commission_rate ?? config('affiliate.default_commission_rate', 0.10);
            $commissionAmount = round($commissionBaseAmount * $commissionRate, 2);

            $commissions->push([
                'affiliate_id' => $affiliate->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'commission_rate' => $commissionRate,
                'commission_base_amount' => $commissionBaseAmount,
                'commission_amount' => $commissionAmount,
                'discount_amount_applied' => $discountAmountApplied,
                'coupon_code' => $order->coupon_code,
                'status' => config('affiliate.auto_approve_event', false) ? 'approved' : 'pending',
            ]);
        }

        return $commissions;
    }

    public function createCommissions(Order $order): Collection
    {
        $existingCount = AffiliateCommission::query()
            ->where('order_id', $order->id)
            ->count();

        if ($existingCount > 0) {
            return collect();
        }

        $commissions = $this->calculateCommissions($order);

        if ($commissions->isEmpty()) {
            return $commissions;
        }

        return DB::transaction(function () use ($commissions) {
            $created = collect();

            foreach ($commissions as $data) {
                $created->push(AffiliateCommission::create($data));
            }

            return $created;
        });
    }
}
