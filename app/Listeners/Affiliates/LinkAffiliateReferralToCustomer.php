<?php

declare(strict_types=1);

namespace App\Listeners\Affiliates;

use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Events\Customers\CustomerRegistered;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class LinkAffiliateReferralToCustomer
{
    public function handle(CustomerRegistered $event): void
    {
        $visitorToken = Session::get('affiliate_referral_token') ?? Cookie::get('affiliate_referral');

        if (! $visitorToken) {
            return;
        }

        AffiliateReferral::query()
            ->where('visitor_token', $visitorToken)
            ->whereNull('user_id')
            ->update(['user_id' => $event->user->id]);
    }
}
