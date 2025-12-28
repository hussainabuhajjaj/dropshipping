<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Order\StoreOrderRequest;
use App\Http\Requests\Api\V1\Order\UpdateOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\OrderCollection;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends ApiController
{
    public function __construct(private OrderService $service) {}

    /**
     * List orders with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $orders = $this->service->paginate($request);

        return $this->success(
            new OrderCollection($orders),
            'Orders retrieved successfully'
        );
    }

    /**
     * Get order statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $stats = $this->service->getStatistics($request);

        return $this->success(
            $stats,
            'Order statistics retrieved successfully'
        );
    }

    /**
     * Display a specific order.
     */
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order = $this->service->show($order);

        return $this->success(
            new OrderResource($order),
            'Order retrieved successfully'
        );
    }

    /**
     * Create a new order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        try {
            $order = $this->service->create(
                $request->validated(),
                $request->input('items')
            );

            return $this->created(
                new OrderResource($order),
                'Order created successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to create order: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Update an order.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        try {
            $order = $this->service->update($order, $request->validated());

            return $this->success(
                new OrderResource($order),
                'Order updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to update order: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $request->validate(['status' => 'required|string|in:pending,processing,shipped,delivered,cancelled']);

        try {
            $order = $this->service->updateStatus($order, $request->input('status'));

            return $this->success(
                new OrderResource($order),
                'Order status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to update order status: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Update payment status.
     */
    public function updatePaymentStatus(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $request->validate(['payment_status' => 'required|string|in:unpaid,paid,refunded,failed']);

        try {
            $order = $this->service->updatePaymentStatus($order, $request->input('payment_status'));

            return $this->success(
                new OrderResource($order),
                'Payment status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to update payment status: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Delete an order.
     */
    public function destroy(Order $order): JsonResponse
    {
        Gate::authorize('delete', $order);

        try {
            $this->service->delete($order);

            return $this->deleted('Order deleted successfully');
        } catch (\Exception $e) {
            return $this->error(
                'Failed to delete order: ' . $e->getMessage(),
                400
            );
        }
    }
}
