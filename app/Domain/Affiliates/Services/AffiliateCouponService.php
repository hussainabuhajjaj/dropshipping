<?php

declare(strict_types=1);

namespace App\Domain\Affiliates\Services;

use App\Domain\Affiliates\Models\Affiliate;
use App\Models\Coupon;

class AffiliateCouponService
{
    public function createAffiliateCoupon(Affiliate $affiliate, array $data): Coupon
    {
        $code = $data['code'] ?? $this->generateUniqueCouponCode($affiliate);

        return Coupon::create([
            'code' => $code,
            'type' => $data['type'] ?? 'percent',
            'amount' => $data['amount'] ?? 0,
            'description' => $data['description'] ?? sprintf('Affiliate coupon for %s', $affiliate->name),
            'affiliate_id' => $affiliate->id,
            'affects_affiliate_commission' => $data['affects_affiliate_commission'] ?? true,
            'is_active' => $data['is_active'] ?? true,
            'max_uses' => $data['max_uses'] ?? 0,
            'min_order_total' => $data['min_order_total'] ?? null,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
        ]);
    }

    public function generateUniqueCouponCode(Affiliate $affiliate, int $suffixLength = 3): string
    {
        $prefix = strtoupper($affiliate->referral_code);

        do {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, $suffixLength));
            $code = $prefix . $suffix;
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }

    public function createStandardAffiliateCoupon(
        Affiliate $affiliate,
        float $discountPercent = 10,
        int $maxUses = 0,
        ?int $validDays = 30,
    ): Coupon {
        $code = $this->generateUniqueCouponCode($affiliate);

        return Coupon::create([
            'code' => $code,
            'type' => 'percent',
            'amount' => $discountPercent,
            'description' => sprintf('Referral discount from %s', $affiliate->name),
            'affiliate_id' => $affiliate->id,
            'affects_affiliate_commission' => true,
            'is_active' => true,
            'max_uses' => $maxUses,
            'starts_at' => now(),
            'ends_at' => $validDays ? now()->addDays($validDays) : null,
        ]);
    }

    public function validateAffiliateCouponAssignment(Coupon $coupon, Affiliate $affiliate): bool
    {
        if ((int) $coupon->affiliate_id !== (int) $affiliate->id) {
            return false;
        }

        $affiliate->refresh();

        if ($affiliate->status === 'suspended') {
            return false;
        }

        return true;
    }

    public function findOrCreateReferralCoupon(Affiliate $affiliate): Coupon
    {
        $coupon = Coupon::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->where(function ($q) {
                $q->where('max_uses', 0)->orWhereColumn('uses', '<', 'max_uses');
            })
            ->first();

        if ($coupon) {
            return $coupon;
        }

        return $this->createStandardAffiliateCoupon(
            $affiliate,
            (int) (config('affiliate.referral_discount_percent', 10)),
            (int) (config('affiliate.referral_coupon_max_uses', 0)),
            (int) (config('affiliate.referral_coupon_valid_days', 30)),
        );
    }
}
