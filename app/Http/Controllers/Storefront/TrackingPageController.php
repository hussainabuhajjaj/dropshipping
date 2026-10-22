<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $orderNumber = $request->query('number');
        $email = $request->query('email');
        $tracking = null;
        $error = null;

        if ($orderNumber && $email) {
            $order = Order::query()
                ->where('number', $orderNumber)
                ->where('email', $email)
                ->with(['orderItems.shipments.trackingEvents'])
                ->first();

            if (! $order) {
                $error = 'Order not found. Please check your details.';
            } else {
                $tracking = $this->buildTrackingPayload($order);
            }
        }

        return Inertia::render('Orders/Tracking', [
            'tracking' => $tracking,
            'lookup' => [
                'number' => $orderNumber,
                'email' => $email,
            ],
            'error' => $error,
        ]);
    }

    private function buildTrackingPayload(Order $order): array
    {
        $shipments = $order->orderItems->flatMap(function ($item) {
            return $item->shipments->map(function ($shipment) use ($item) {
                return [
                    'order_item_id' => $item->id,
                    'tracking_number' => $shipment->tracking_number,
                    'carrier' => $shipment->carrier,
                    'tracking_url' => $shipment->tracking_url,
                    'shipped_at' => $shipment->shipped_at,
                    'delivered_at' => $shipment->delivered_at,
                    'events' => $shipment->trackingEvents
                        ->sortByDesc('occurred_at')
                        ->values()
                        ->map(function ($event) {
                            return [
                                'status_code' => $event->status_code,
                                'status_label' => $event->status_label,
                                'description' => $event->description,
                                'location' => $event->location,
                                'occurred_at' => $event->occurred_at,
                            ];
                        }),
                ];
            });
        })->values();

        return [
            'order_number' => $order->number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'shipments' => $shipments,
        ];
    }
}
