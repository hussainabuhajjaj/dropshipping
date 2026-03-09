<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        $rawStatus = $order->customer_status ?? $order->status;
        $internalStatuses = ['pending', 'paid', 'fulfilling', 'fulfilled', 'cancelled'];
        $statusKey = in_array($rawStatus, $internalStatuses, true)
            ? match ($rawStatus) {
                'pending', 'paid' => 'received',
                'fulfilling', 'fulfilled' => 'processing',
                'cancelled' => 'refunded',
                default => 'processing',
            }
            : ($rawStatus ?? 'processing');
        $payment = $this->resolveLatestPayment($order);

        return [
            'number' => $order->number,
            'status' => $order->getCustomerStatusLabel(),
            'statusKey' => $statusKey,
            'statusExplanation' => $order->getCustomerStatusExplanation(),
            'currency' => (string) ($order->currency ?? 'USD'),
            'subtotal' => (float) ($order->subtotal ?? 0),
            'shippingTotal' => (float) ($order->shipping_total ?? 0),
            'taxTotal' => (float) ($order->tax_total ?? 0),
            'discountTotal' => (float) ($order->discount_total ?? 0),
            'total' => (float) $order->grand_total,
            'paidAmount' => $payment?->amount !== null ? (float) $payment->amount : null,
            'paidCurrency' => $payment?->currency,
            'placedAt' => $order->placed_at?->toDateString(),
            'items' => OrderItemResource::collection($order->orderItems),
            'tracking' => TrackingEventResource::collection($this->buildTrackingEvents($order)),
        ];
    }

    private function resolveLatestPayment(Order $order): ?Payment
    {
        $payments = $order->relationLoaded('payments')
            ? collect($order->payments)
            : $order->payments()->latest('paid_at')->latest('id')->get();

        $preferred = $payments->first(
            fn ($payment) => in_array((string) $payment->status, ['paid', 'success'], true)
        );

        return $preferred ?: $payments->first();
    }

    private function buildTrackingEvents(Order $order): array
    {
        $shipmentEvents = $order->orderItems->flatMap(function (OrderItem $item) {
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

        if ($shipmentEvents->isNotEmpty()) {
            return $shipmentEvents->sortByDesc('occurredAt')->values()->all();
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
