<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductCollection;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Services\ProductService;

class ProductController extends ApiController
{
    public function __construct(private ProductService $service) {}
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        // Check authorization
        Gate::authorize('viewAny', Product::class);

        $products = $this->service->paginate($request);

        return $this->success(
            new ProductCollection($products),
            'Products retrieved successfully'
        );
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        // Check authorization
        Gate::authorize('create', Product::class);

        try {
            $product = $this->service->create($request->validated(), $request->input('images'));

            return $this->created(
                new ProductResource($product),
                'Product created successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to create product: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        // Check authorization
        Gate::authorize('view', $product);

        $product->load(['category', 'images']);
        
        // Increment views count
        $product->increment('views_count');

        return $this->success(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        // Check authorization
        Gate::authorize('update', $product);

        try {
            $product = $this->service->update($product, $request->validated(), $request->has('images') ? $request->input('images') : null);

            return $this->success(
                new ProductResource($product),
                'Product updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error(
                'Failed to update product: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check authorization
        Gate::authorize('delete', $product);

        try {
            $this->service->delete($product);

            return $this->deleted('Product deleted successfully');
        } catch (\Exception $e) {
            return $this->error(
                'Failed to delete product: ' . $e->getMessage(),
                500
            );
        }
    }
}
