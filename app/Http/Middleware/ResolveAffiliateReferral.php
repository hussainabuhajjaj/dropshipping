<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Domain\Affiliates\Services\AffiliateCouponService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class ResolveAffiliateReferral
{
    public function __construct(
        private readonly AffiliateCouponService $couponService,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $ref = $request->query('ref');

        if (! $ref) {
            return $next($request);
        }

        $affiliate = Affiliate::query()
            ->where('referral_code', $ref)
            ->where('status', '!=', 'suspended')
            ->first();

        if (! $affiliate) {
            session()->forget(['affiliate_referral_token', 'affiliate_referral_code']);
            Cookie::queue(Cookie::forget('affiliate_referral'));

            return $next($request);
        }

        $token = session('affiliate_referral_token') ?? $request->cookie('affiliate_referral') ?? Str::random(40);

        AffiliateReferral::updateOrCreate(
            ['visitor_token' => $token],
            [
                'affiliate_id' => $affiliate->id,
                'expires_at' => now()->addDays(config('affiliate.cookie_lifetime_days', 30)),
            ]
        );

        $coupon = $this->couponService->findOrCreateReferralCoupon($affiliate);

        Cookie::queue('affiliate_referral', $token, 60 * 24 * config('affiliate.cookie_lifetime_days', 30));
        session([
            'affiliate_referral_token' => $token,
            'affiliate_referral_code' => $ref,
            'affiliate_referral_coupon_code' => $coupon->code,
            'affiliate_referral_discount' => $coupon->amount,
            'affiliate_referral_processed' => false,
        ]);

        return $next($request);
    }
}
