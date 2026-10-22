<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OrderItem;
use App\Notifications\ReviewRequestNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RequestProductReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function handle(): void
    {
        $delayDays = app(\App\Models\SiteSetting::class)->value('review_request_delay_days') ?? 7;
        $deliveredAt = now()->subDays($delayDays);

        OrderItem::query()
            ->with(['order.customer', 'productVariant.product', 'shipments'])
            ->where('fulfillment_status', 'fulfilled')
            ->whereHas('shipments', function ($builder) use ($deliveredAt) {
                $builder
                    ->whereNotNull('delivered_at')
                    ->where('delivered_at', '<=', $deliveredAt);
            })
            ->whereDoesntHave('review')
            ->limit(50)
            ->get()
            ->each(function (OrderItem $item) {
                $customer = $item->order?->customer;
                if (! $customer || ! $customer->email) {
                    return;
                }

                $customer->notify(new ReviewRequestNotification($item));
            });
    }
}
