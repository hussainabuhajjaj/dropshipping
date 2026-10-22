<?php

declare(strict_types=1);

namespace App\Domain\Affiliates\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class AffiliateReferralDiscountService
{
    public function getPendingReferralCoupon(): ?Coupon
    {
        $couponCode = Session::get('affiliate_referral_coupon_code');
        if (! $couponCode) {
            return null;
        }

        $alreadyApplied = Session::get('cart_coupon');
        if ($alreadyApplied && ($alreadyApplied['code'] ?? null) === $couponCode) {
            return null;
        }

        if ($alreadyApplied) {
            return null;
        }

        $coupon = Coupon::query()
            ->where('code', $couponCode)
            ->where('is_active', true)
            ->whereNotNull('affiliate_id')
            ->first();

        if (! $coupon) {
            return null;
        }

        if (! $coupon->isCurrentlyValid()) {
            return null;
        }

        return $coupon;
    }

    public function autoApplyReferralCoupon(): ?Coupon
    {
        $coupon = $this->getPendingReferralCoupon();
        if (! $coupon) {
            return null;
        }

        $discountPercent = Session::get('affiliate_referral_discount', $coupon->amount);

        Session::put('cart_coupon', [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'affiliate_referral' => true,
        ]);

        return $coupon;
    }

    public function isReferralCouponApplied(): bool
    {
        $cartCoupon = Session::get('cart_coupon');
        if (! $cartCoupon) {
            return false;
        }

        return ! empty($cartCoupon['affiliate_referral']);
    }

    public function clearReferralSession(): void
    {
        Session::forget([
            'affiliate_referral_token',
            'affiliate_referral_code',
            'affiliate_referral_coupon_code',
            'affiliate_referral_discount',
            'affiliate_referral_processed',
        ]);
        Cookie::queue(Cookie::forget('affiliate_referral'));
    }
}
