<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Promotion;
use App\Services\Notifications\NotificationBroadcastService;
use Illuminate\Support\Facades\Log;

class PromotionObserver
{
    public function __construct(private readonly NotificationBroadcastService $notifications)
    {
    }

    public function created(Promotion $promotion): void
    {
        if ($promotion->is_active) {
            $this->notifications->broadcastPromotion($promotion);
        }
    }

    public function updated(Promotion $promotion): void
    {
        $changes = $promotion->getChanges();

        if (array_key_exists('is_active', $changes) && $promotion->is_active) {
            Log::info('Promotion activated; broadcasting push', [
                'promotion_id' => $promotion->id,
            ]);
            $this->notifications->broadcastPromotion($promotion);
        }
    }
}
