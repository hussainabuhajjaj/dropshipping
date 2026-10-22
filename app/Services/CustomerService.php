<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;

class CustomerService
{
    /**
     * Get paginated customers with optional filtering.
     */
    public function paginate(Request $request): Paginator
    {
        $query = Customer::query();
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by city
        if ($city = $request->input('city')) {
            $query->where('city', $city);
        }

        // Filter by country
        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        // Filter by registration date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['name', 'email', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get a single customer by ID.
     */
    public function show(Customer $customer): Customer
    {
        return $customer->load('orders');
    }

    /**
     * Create a new customer.
     */
    public function create(array $data): Customer
    {
        return Customer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'country' => $data['country'] ?? null,
        ]);
    }

    /**
     * Update a customer.
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name' => $data['name'] ?? $customer->name,
            'email' => $data['email'] ?? $customer->email,
            'phone' => $data['phone'] ?? $customer->phone,
            'address' => $data['address'] ?? $customer->address,
            'city' => $data['city'] ?? $customer->city,
            'state' => $data['state'] ?? $customer->state,
            'zip' => $data['zip'] ?? $customer->zip,
            'country' => $data['country'] ?? $customer->country,
        ]);

        return $customer;
    }

    /**
     * Delete a customer.
     */
    public function delete(Customer $customer): bool
    {
        return $customer->delete();
    }

    /**
     * Get customer statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_customers' => Customer::count(),
            'total_revenue' => Customer::with('orders')
                ->get()
                ->sum(function ($customer) {
                    return $customer->orders->sum('total');
                }),
            'average_order_value' => $this->getAverageOrderValue(),
            'customers_by_country' => Customer::selectRaw('country, COUNT(*) as count')
                ->groupBy('country')
                ->pluck('count', 'country')
                ->toArray(),
        ];
    }

    /**
     * Calculate average order value across all customers.
     */
    private function getAverageOrderValue(): float
    {
        $totalRevenue = Customer::with('orders')
            ->get()
            ->sum(function ($customer) {
                return $customer->orders->sum('total');
            });

        $totalOrders = \App\Models\Order::count();

        return $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
    }

    /**
     * Get customers by order count.
     */
    public function getTopCustomers(int $limit = 10): Collection
    {
        return Customer::withCount('orders')
            ->orderByDesc('orders_count')
            ->limit($limit)
            ->get();
    }
}
