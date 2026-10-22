<?php

declare(strict_types=1);

namespace App\Listeners\Affiliates;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Domain\Affiliates\Services\AffiliateCommissionService;
use App\Domain\Affiliates\Services\CommissionService;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderPlaced;
use App\Mail\AffiliateCommissionEarned;
use App\Models\Coupon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class ProcessAffiliateOrderReferral
{
    public function __construct(
        private readonly AffiliateCommissionService $commissionService,
        private readonly CommissionService $commissionCalculationService,
    ) {
    }

    public function handle(OrderPlaced|OrderPaid $event): void
    {
        $order = $event->order->loadMissing('orderItems');

        $affiliate = $this->resolveAffiliate($order);

        if (! $affiliate || $affiliate->status === 'suspended') {
            return;
        }

        if (AffiliateCommission::query()->where('order_id', $order->id)->exists()) {
            return;
        }

        $createdCommissions = [];

        foreach ($order->orderItems as $item) {
            if (AffiliateCommission::query()->where('order_item_id', $item->id)->exists()) {
                continue;
            }

            $createdCommissions[] = $this->commissionService->createCommission($order, $item, $affiliate);
        }

        if (empty($createdCommissions)) {
            Session::put('affiliate_referral_processed', true);
            return;
        }

        foreach ($createdCommissions as $commission) {
            $recipient = $commission->affiliate?->email;

            if (! $recipient) {
                continue;
            }

            try {
                Mail::to($recipient)->send(new AffiliateCommissionEarned($commission));
            } catch (\Throwable $exception) {
                Log::warning('Failed to send affiliate commission email', [
                    'affiliate_id' => $commission->affiliate_id,
                    'order_id' => $commission->order_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        Session::put('affiliate_referral_processed', true);
        Session::forget(['affiliate_referral_token', 'affiliate_referral_code']);
    }

    private function resolveAffiliate($order): ?Affiliate
    {
        if ($order->coupon_code) {
            $coupon = Coupon::query()
                ->where('code', $order->coupon_code)
                ->whereNotNull('affiliate_id')
                ->first();

            if ($coupon && $coupon->affiliate_id) {
                $affiliate = $coupon->affiliate;
                if ($affiliate && $affiliate->status !== 'suspended') {
                    return $affiliate;
                }
            }
        }

        $visitorToken = Session::get('affiliate_referral_token')
            ?? Cookie::get('affiliate_referral');

        if (! $visitorToken || Session::get('affiliate_referral_processed')) {
            return null;
        }

        $referral = AffiliateReferral::query()
            ->where('visitor_token', $visitorToken)
            ->where('expires_at', '>', now())
            ->first();

        if (! $referral) {
            return null;
        }

        $affiliate = $referral->affiliate;

        if (! $affiliate || $affiliate->status === 'suspended') {
            return null;
        }

        return $affiliate;
    }
}
