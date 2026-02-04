<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Order\OrderIndexRequest;
use App\Http\Requests\Api\Storefront\Order\OrderShowRequest;
use App\Http\Resources\Storefront\OrderDetailResource;
use App\Http\Resources\Storefront\OrderSummaryResource;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 12), 50);
        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->latest('placed_at')
            ->paginate($perPage);

        return response()->json([
            'orders' => OrderSummaryResource::collection($orders->getCollection()),
            'pagination' => [
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'perPage' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(OrderShowRequest $request, Order $order): JsonResponse
    {
        $customer = $request->user();
        if (! $customer || $order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $order->load([
            'orderItems.productVariant.product.images',
            'orderItems.shipments.trackingEvents',
            'events',
        ]);

        return response()->json([
            'order' => new OrderDetailResource($order),
        ]);
    }
}
