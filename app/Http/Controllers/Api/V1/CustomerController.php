<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\ResourceCollection;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerController extends ApiController
{
    public function __construct(private CustomerService $service) {}

    /**
     * List customers with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = $this->service->paginate($request);

        return $this->success(
            new ResourceCollection($customers, CustomerResource::class),
            'Customers retrieved successfully'
        );
    }

    /**
     * Get customer statistics.
     */
    public function statistics(): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $stats = $this->service->getStatistics();

        return $this->success(
            $stats,
            'Customer statistics retrieved successfully'
        );
    }

    /**
     * Get top customers by order count.
     */
    public function topCustomers(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $limit = (int) $request->input('limit', 10);
        $customers = $this->service->getTopCustomers($limit);

        return $this->success(
            CustomerResource::collection($customers),
            'Top customers retrieved successfully'
        );
    }

    /**
     * Show a single customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        $customer = $this->service->show($customer);

        return $this->success(
            new CustomerResource($customer),
            'Customer retrieved successfully'
        );
    }

    /**
     * Create a new customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        try {
            $customer = $this->service->create($request->validated());

            return $this->created(
                new CustomerResource($customer),
                'Customer created successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to create customer: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Update a customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        try {
            $customer = $this->service->update($customer, $request->validated());

            return $this->success(
                new CustomerResource($customer),
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to update customer: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Delete a customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        try {
            $this->service->delete($customer);

            return $this->deleted('Customer deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete customer: ' . $e->getMessage(), 400);
        }
    }
}
