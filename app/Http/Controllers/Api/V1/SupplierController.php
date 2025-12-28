<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Supplier\StoreSupplierRequest;
use App\Http\Requests\Api\V1\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Api\V1\SupplierResource;
use App\Http\Resources\Api\V1\ResourceCollection;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupplierController extends ApiController
{
    public function __construct(private SupplierService $service) {}

    /**
     * List suppliers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $suppliers = $this->service->paginate($request);

        return $this->success(
            new ResourceCollection($suppliers, SupplierResource::class),
            'Suppliers retrieved successfully'
        );
    }

    /**
     * Get supplier statistics.
     */
    public function statistics(): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $stats = $this->service->getStatistics();

        return $this->success(
            $stats,
            'Supplier statistics retrieved successfully'
        );
    }

    /**
     * Get top suppliers by rating.
     */
    public function topSuppliers(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $limit = (int) $request->input('limit', 10);
        $suppliers = $this->service->getTopSuppliers($limit);

        return $this->success(
            SupplierResource::collection($suppliers),
            'Top suppliers retrieved successfully'
        );
    }

    /**
     * Show a single supplier.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        Gate::authorize('view', $supplier);

        $supplier = $this->service->show($supplier);

        return $this->success(
            new SupplierResource($supplier),
            'Supplier retrieved successfully'
        );
    }

    /**
     * Create a new supplier.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        try {
            $supplier = $this->service->create($request->validated());

            return $this->created(
                new SupplierResource($supplier),
                'Supplier created successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to create supplier: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Update a supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('update', $supplier);

        try {
            $supplier = $this->service->update($supplier, $request->validated());

            return $this->success(
                new SupplierResource($supplier),
                'Supplier updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to update supplier: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Delete a supplier.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);

        try {
            $this->service->delete($supplier);

            return $this->deleted('Supplier deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete supplier: ' . $e->getMessage(), 400);
        }
    }
}
