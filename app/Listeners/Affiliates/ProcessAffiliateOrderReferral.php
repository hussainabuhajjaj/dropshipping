<?php

declare(strict_types=1);

namespace App\Listeners\Affiliates;

use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Domain\Affiliates\Services\AffiliateCommissionService;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderPlaced;
use App\Mail\AffiliateCommissionEarned;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class ProcessAffiliateOrderReferral
{
    public function __construct(
        private AffiliateCommissionService $commissionService,
    ) {
    }

    public function handle(OrderPlaced|OrderPaid $event): void
    {
        $order = $event->order->loadMissing('orderItems');

        $visitorToken = Session::get('affiliate_referral_token')
            ?? Cookie::get('affiliate_referral');

        if (! $visitorToken || Session::get('affiliate_referral_processed')) {
            return;
        }

        $referral = AffiliateReferral::query()
            ->where('visitor_token', $visitorToken)
            ->where('expires_at', '>', now())
            ->first();

        if (! $referral) {
            return;
        }

        $affiliate = $referral->affiliate;
        if (! $affiliate || $affiliate->status === 'suspended') {
            return;
        }

        $createdCommission = [];

        foreach ($order->orderItems as $item) {
            if (AffiliateCommission::query()->where('order_item_id', $item->id)->exists()) {
                continue;
            }

            $createdCommission[] = $this->commissionService->createCommission($order, $item, $affiliate);
        }

        if (empty($createdCommission)) {
            Session::put('affiliate_referral_processed', true);

            return;
        }

        foreach ($createdCommission as $commission) {
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
}
