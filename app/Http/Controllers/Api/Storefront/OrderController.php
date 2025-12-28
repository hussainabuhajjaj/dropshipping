<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $perPage = min((int) $request->query('per_page', 12), 50);
        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->latest('placed_at')
            ->paginate($perPage);

        $orders->getCollection()->transform(fn (Order $order) => [
            'number' => $order->number,
            'status' => $order->getCustomerStatusLabel(),
            'total' => (float) $order->grand_total,
            'placedAt' => $order->placed_at?->toDateString(),
        ]);

        return response()->json([
            'orders' => $orders->items(),
            'pagination' => [
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'perPage' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user('customer');
        if (! $customer || $order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $order->load([
            'orderItems.productVariant.product.images',
            'orderItems.shipments.trackingEvents',
            'events',
        ]);

        $items = $order->orderItems->map(function (OrderItem $item) {
            $product = $item->productVariant?->product;
            $image = $product?->images?->sortBy('position')->first()?->url;

            return [
                'id' => $item->id,
                'name' => $item->snapshot['name'] ?? $product?->name ?? 'Item',
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price,
                'image' => $image,
            ];
        })->values()->all();

        $tracking = $this->buildTrackingEvents($order);

        return response()->json([
            'order' => [
                'number' => $order->number,
                'status' => $order->getCustomerStatusLabel(),
                'total' => (float) $order->grand_total,
                'placedAt' => $order->placed_at?->toDateString(),
                'items' => $items,
                'tracking' => $tracking,
            ],
        ]);
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
