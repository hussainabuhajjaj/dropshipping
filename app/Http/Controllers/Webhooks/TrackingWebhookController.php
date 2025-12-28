<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Orders\Services\TrackingService;
use App\Events\Orders\OrderDelivered;
use App\Events\Orders\OrderShipped;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackingWebhookController extends Controller
{
    public function __invoke(string $provider, Request $request, TrackingService $trackingService): JsonResponse
    {
        $payload = $request->all();
        $orderNumber = $payload['order_number'] ?? null;

        if (! $orderNumber) {
            return response()->json(['error' => 'Missing order number'], Response::HTTP_BAD_REQUEST);
        }

        $order = Order::query()
            ->where('number', $orderNumber)
            ->with(['orderItems.shipments'])
            ->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $orderItem = null;
        $orderItemId = $payload['order_item_id'] ?? null;
        $trackingNumber = $payload['tracking_number'] ?? null;

        if ($orderItemId) {
            $orderItem = $order->orderItems->firstWhere('id', (int) $orderItemId);
        }

        if (! $orderItem && $trackingNumber) {
            $orderItem = $order->orderItems->first(function ($item) use ($trackingNumber) {
                return $item->shipments->contains('tracking_number', $trackingNumber);
            });
        }

        if (! $orderItem && $order->orderItems->count() === 1) {
            $orderItem = $order->orderItems->first();
        }

        if (! $orderItem) {
            return response()->json(['error' => 'Unable to resolve order item'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $trackingNumber && $orderItem->shipments->isEmpty()) {
            return response()->json(['error' => 'Tracking number is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $shipment = $trackingService->recordShipment($orderItem, [
            'tracking_number' => $trackingNumber ?? $orderItem->shipments->first()?->tracking_number,
            'carrier' => $payload['carrier'] ?? null,
            'tracking_url' => $payload['tracking_url'] ?? null,
            'shipped_at' => $payload['shipped_at'] ?? now(),
            'delivered_at' => $payload['delivered_at'] ?? null,
            'raw_events' => $payload['events'] ?? null,
        ]);

        $events = $payload['events'] ?? [];
        if (is_array($events)) {
            $events = array_values(array_filter($events, function ($event) {
                return is_array($event) && isset($event['status_code'], $event['occurred_at']);
            }));
            $trackingService->syncFromWebhook($shipment, $events);
        }

        if ($shipment->delivered_at) {
            $orderItem->update(['fulfillment_status' => 'fulfilled']);
            $allDelivered = $order->orderItems()->where('fulfillment_status', '!=', 'fulfilled')->doesntExist();
            if ($allDelivered) {
                $order->update(['status' => 'fulfilled']);
                event(new OrderDelivered($order, (string) $shipment->delivered_at));
            }
        } elseif ($shipment->shipped_at) {
            if (! in_array($orderItem->fulfillment_status, ['fulfilled', 'failed', 'cancelled'], true)) {
                $orderItem->update(['fulfillment_status' => 'fulfilling']);
            }
            if (! in_array($order->status, ['fulfilled', 'cancelled', 'refunded'], true)) {
                $order->update(['status' => 'fulfilling']);
                // Dispatch order shipped event to trigger notifications
                event(new OrderShipped($order, $shipment->tracking_number));
            }
        }

        return response()->json([
            'success' => true,
            'order_number' => $order->number,
            'order_item_id' => $orderItem->id,
            'tracking_number' => $shipment->tracking_number,
            'provider' => $provider,
        ]);
    }
}
