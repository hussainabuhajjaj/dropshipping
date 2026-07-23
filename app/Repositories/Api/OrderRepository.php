<?php

declare(strict_types=1);

namespace App\Repositories\Api;

use App\Contracts\Orders\OrderRepositoryContract;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryContract
{
    public function findByCustomer(int $customerId, int $perPage = 20): LengthAwarePaginator
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->with(['items.product.images', 'items.variant', 'payment', 'shippingMethod'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findById(int $id, ?int $customerId = null): ?object
    {
        $query = Order::query()
            ->with(['items.product.images', 'items.variant', 'payment', 'shippingMethod', 'address']);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $query->find($id);
    }

    public function findByNumber(string $number): ?object
    {
        return Order::query()
            ->where('number', $number)
            ->with(['items.product.images', 'items.variant', 'payment', 'shippingMethod', 'address'])
            ->first();
    }

    public function getTracking(string $orderNumber): array
    {
        $order = $this->findByNumber($orderNumber);

        if (! $order) {
            return [];
        }

        return [
            'order_number' => $order->number,
            'status'       => $order->status,
            'payment_status' => $order->payment_status,
            'items' => $order->items->map(fn ($i) => [
                'product_name' => $i->product?->name,
                'quantity'     => $i->quantity,
                'variant'      => $i->variant?->title,
            ])->all(),
        ];
    }
}
