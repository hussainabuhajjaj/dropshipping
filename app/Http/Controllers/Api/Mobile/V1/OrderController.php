<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Orders\Models\Order;
use App\Http\Requests\Api\Mobile\V1\Order\OrderIndexRequest;
use App\Http\Requests\Api\Mobile\V1\Order\TrackOrderRequest;
use App\Http\Resources\Mobile\V1\OrderDetailResource;
use App\Http\Resources\Mobile\V1\OrderSummaryResource;
use App\Http\Resources\Mobile\V1\OrderTrackingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer) {
            return $this->unauthorized();
        }

        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 12), 50);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->latest('placed_at')
            ->paginate($perPage);

        return $this->success(
            OrderSummaryResource::collection($orders->getCollection()),
            null,
            200,
            [
                'currentPage' => $orders->currentPage(),
                'lastPage' => $orders->lastPage(),
                'perPage' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user();
        if (! $customer || $order->customer_id !== $customer->id) {
            return $this->notFound('Not found');
        }

        $order->load([
            'orderItems.productVariant.product.images',
            'orderItems.shipments.trackingEvents',
            'events',
        ]);

        return $this->success(new OrderDetailResource($order));
    }

    public function track(TrackOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = Order::query()
            ->where('number', $validated['number'])
            ->where('email', $validated['email'])
            ->with(['orderItems.shipments.trackingEvents', 'events'])
            ->first();

        if (! $order) {
            return $this->notFound('Order not found');
        }

        return $this->success(new OrderTrackingResource($order));
    }
}
