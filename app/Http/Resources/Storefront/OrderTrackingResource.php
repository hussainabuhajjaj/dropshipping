<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Http\Request;

class OrderTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'orderNumber' => $order->number,
            'status' => $order->getCustomerStatusLabel(),
            'tracking' => TrackingEventResource::collection($this->buildTrackingEvents($order)),
        ];
    }

    private function buildTrackingEvents(Order $order): array
    {
        $events = $order->orderItems->flatMap(function (OrderItem $item) {
            return $item->shipments->flatMap(function ($shipment) {
                return $shipment->trackingEvents->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'status' => $event->status_label,
                        'description' => $event->description,
                        'occurredAt' => $event->occurred_at?->format('Y-m-d H:i') ?? null,
                    ];
                });
            });
        });

        if ($events->isNotEmpty()) {
            return $events->sortByDesc('occurredAt')->values()->all();
        }

        return $order->events
            ->sortByDesc('created_at')
            ->map(fn ($event) => [
                'id' => $event->id,
                'status' => $event->status ?? $event->type ?? 'Update',
                'description' => $event->message ?? 'Order update',
                'occurredAt' => $event->created_at?->format('Y-m-d H:i') ?? null,
            ])
            ->values()
            ->all();
    }
}
