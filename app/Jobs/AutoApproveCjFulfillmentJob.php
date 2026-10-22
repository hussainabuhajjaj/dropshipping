<?php

namespace App\Jobs;

use App\Domain\Fulfillment\Services\FulfillmentDispatchService;
use App\Domain\Orders\Models\OrderItem;
use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoApproveCjFulfillmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FulfillmentDispatchService $dispatchService): void
    {
        $delayHours = (int) (SiteSetting::query()->value('cj_auto_approve_delay_hours') ?? 24);
        $cutoff = now()->subHours($delayHours);

        $items = OrderItem::whereHas('fulfillmentProvider', function ($q) {
                $q->where('code', 'cj');
            })
            ->where('fulfillment_status', 'pending')
            ->whereHas('order', function ($q) use ($cutoff) {
                $q->where('placed_at', '<=', $cutoff)
                    ->where('payment_status', 'paid');
            })
            ->get();

        foreach ($items as $item) {
            if (! $dispatchService->dispatchForOrderItem($item)) {
                continue;
            }

            \App\Domain\Orders\Models\OrderAuditLog::create([
                'order_id' => $item->order_id,
                'user_id' => null,
                'action' => 'cj_fulfillment_auto_approved',
                'note' => 'CJ fulfillment auto-approved after delay',
                'payload' => ['order_item_id' => $item->id],
            ]);
        }
    }
}