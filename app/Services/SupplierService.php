<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;

class SupplierService
{
    /**
     * Get paginated suppliers with optional filtering.
     */
    public function paginate(Request $request): Paginator
    {
        $query = Supplier::query();
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Filter by country
        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        // Filter by rating (minimum rating)
        if ($minRating = $request->input('min_rating')) {
            $query->where('rating', '>=', (float) $minRating);
        }

        // Filter by status (active/inactive)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['name', 'rating', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get a single supplier by ID.
     */
    public function show(Supplier $supplier): Supplier
    {
        return $supplier->load('products');
    }

    /**
     * Create a new supplier.
     */
    public function create(array $data): Supplier
    {
        return Supplier::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'country' => $data['country'] ?? null,
            'website' => $data['website'] ?? null,
            'rating' => $data['rating'] ?? 0,
            'lead_time_days' => $data['lead_time_days'] ?? 7,
            'minimum_order_qty' => $data['minimum_order_qty'] ?? 1,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Update a supplier.
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name' => $data['name'] ?? $supplier->name,
            'email' => $data['email'] ?? $supplier->email,
            'company' => $data['company'] ?? $supplier->company,
            'phone' => $data['phone'] ?? $supplier->phone,
            'address' => $data['address'] ?? $supplier->address,
            'city' => $data['city'] ?? $supplier->city,
            'state' => $data['state'] ?? $supplier->state,
            'zip' => $data['zip'] ?? $supplier->zip,
            'country' => $data['country'] ?? $supplier->country,
            'website' => $data['website'] ?? $supplier->website,
            'rating' => $data['rating'] ?? $supplier->rating,
            'lead_time_days' => $data['lead_time_days'] ?? $supplier->lead_time_days,
            'minimum_order_qty' => $data['minimum_order_qty'] ?? $supplier->minimum_order_qty,
            'status' => $data['status'] ?? $supplier->status,
        ]);

        return $supplier;
    }

    /**
     * Delete a supplier.
     */
    public function delete(Supplier $supplier): bool
    {
        return $supplier->delete();
    }

    /**
     * Get supplier statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_suppliers' => Supplier::count(),
            'active_suppliers' => Supplier::where('status', 'active')->count(),
            'average_rating' => Supplier::avg('rating') ?? 0,
            'suppliers_by_country' => Supplier::selectRaw('country, COUNT(*) as count')
                ->whereNotNull('country')
                ->groupBy('country')
                ->pluck('count', 'country')
                ->toArray(),
        ];
    }

    /**
     * Get suppliers with highest ratings.
     */
    public function getTopSuppliers(int $limit = 10)
    {
        return Supplier::where('status', 'active')
            ->orderByDesc('rating')
            ->limit($limit)
            ->get();
    }
}
