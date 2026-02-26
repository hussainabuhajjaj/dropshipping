<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Coupon;
use App\Services\Notifications\NotificationBroadcastService;
use Illuminate\Support\Facades\Log;

class CouponObserver
{
    public function __construct(private readonly NotificationBroadcastService $notifications)
    {
    }

    public function created(Coupon $coupon): void
    {
        if ($coupon->isCurrentlyValid()) {
            $this->notifications->broadcastCoupon($coupon);
        }
    }

    public function updated(Coupon $coupon): void
    {
        $changes = $coupon->getChanges();

        if ($coupon->isCurrentlyValid() && array_key_exists('is_active', $changes)) {
            Log::info('Coupon activated; broadcasting push', [
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
            ]);
            $this->notifications->broadcastCoupon($coupon);
        }
    }
}
