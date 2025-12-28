<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager as DBManager;
use Illuminate\Http\Request;

class OrderService
{
    public function __construct(private DBManager $db) {}

    /**
     * Paginate orders with filtering.
     */
    public function paginate(Request $request, int $maxPerPage = 100): LengthAwarePaginator
    {
        $query = Order::with(['customer', 'orderItems', 'orderItems.product']);

        // Search by order number or customer email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%") // Use 'number' field
                    ->orWhereHas('customer', fn ($q) => $q->where('email', 'like', "%{$search}%"));
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by payment status
        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        // Filter by date range
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Filter by total amount range
        if ($minTotal = $request->input('min_total')) {
            $query->where('grand_total', '>=', $minTotal); // Use 'grand_total' field
        }
        if ($maxTotal = $request->input('max_total')) {
            $query->where('grand_total', '<=', $maxTotal); // Use 'grand_total' field
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if (in_array($sortBy, ['number', 'status', 'grand_total', 'created_at'])) { // Use 'number' and 'grand_total'
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = min((int) $request->input('per_page', 15), $maxPerPage);

        return $query->paginate($perPage);
    }

    /**
     * Get a single order with items.
     */
    public function show(Order $order): Order
    {
        return $order->load(['customer', 'orderItems', 'orderItems.product']);
    }

    /**
     * Create a new order.
     */
    public function create(array $data, ?array $items = null): Order
    {
        return $this->db->transaction(function () use ($data, $items) {
            $order = Order::create([
                'order_number' => $data['order_number'] ?? Order::generateOrderNumber(),
                'customer_id' => $data['customer_id'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'subtotal' => $data['subtotal'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'shipping' => $data['shipping'] ?? 0,
                'total' => $data['total'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($items) {
                foreach ($items as $item) {
                    $order->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                    ]);
                }
            }

            return $order->load(['customer', 'items', 'items.product']);
        });
    }

    /**
     * Update an order.
     */
    public function update(Order $order, array $data): Order
    {
        return $this->db->transaction(function () use ($order, $data) {
            $order->update([
                'status' => $data['status'] ?? $order->status,
                'payment_status' => $data['payment_status'] ?? $order->payment_status,
                'notes' => $data['notes'] ?? $order->notes,
            ]);

            return $order->load(['customer', 'items', 'items.product']);
        });
    }

    /**
     * Update order status.
     */
    public function updateStatus(Order $order, string $status): Order
    {
        $order->update(['status' => $status]);
        return $order->load(['customer', 'items', 'items.product']);
    }

    /**
     * Update payment status.
     */
    public function updatePaymentStatus(Order $order, string $paymentStatus): Order
    {
        $order->update(['payment_status' => $paymentStatus]);
        return $order->load(['customer', 'items', 'items.product']);
    }

    /**
     * Delete an order (only if pending or draft).
     */
    public function delete(Order $order): void
    {
        if (!in_array($order->status, ['pending', 'draft'])) {
            throw new \InvalidArgumentException('Only pending or draft orders can be deleted.');
        }
        $order->delete();
    }

    /**
     * Get order statistics.
     */
    public function getStatistics(Request $request): array
    {
        $query = Order::query();

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return [
            'total_orders' => $query->count(),
            'total_revenue' => (float) $query->sum('total'),
            'average_order_value' => (float) $query->avg('total'),
            'by_status' => $query->groupBy('status')->selectRaw('status, count(*) as count')->pluck('count', 'status'),
            'by_payment_status' => $query->groupBy('payment_status')->selectRaw('payment_status, count(*) as count')->pluck('count', 'payment_status'),
        ];
    }
}
