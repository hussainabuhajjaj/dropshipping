<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $orderNumber = $request->query('number');
        $email = $request->query('email');

        if (! $orderNumber || ! $email) {
            return response()->json(['error' => 'Order number and email are required.'], 422);
        }

        $order = Order::query()
            ->where('number', $orderNumber)
            ->where('email', $email)
            ->with(['orderItems.shipments.trackingEvents'])
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json([
            'orderNumber' => $order->number,
            'status' => $order->getCustomerStatusLabel(),
            'tracking' => $this->buildTrackingEvents($order),
        ]);
    }

    private function buildTrackingEvents(Order $order): array
    {
        $events = $order->orderItems->flatMap(function ($item) {
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

        return $events->sortByDesc('occurredAt')->values()->all();
    }
}
