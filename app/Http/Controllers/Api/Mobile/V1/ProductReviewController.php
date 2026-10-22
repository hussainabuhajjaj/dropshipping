<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Products\Services\ProductReviewService;
use App\Http\Requests\Api\Mobile\V1\Reviews\ReviewIndexRequest;
use App\Http\Requests\Api\Mobile\V1\Reviews\ReviewStoreRequest;
use App\Http\Resources\Mobile\V1\ProductReviewResource;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends ApiController
{
    public function index(ReviewIndexRequest $request, Product $product): JsonResponse
    {
        if (! $product->is_active) {
            return $this->notFound('Product not found');
        }

        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 20), 50);

        $reviews = ProductReview::query()
            ->with('customer')
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->latest()
            ->paginate($perPage);

        return $this->success(
            ProductReviewResource::collection($reviews->getCollection()),
            null,
            200,
            [
                'currentPage' => $reviews->currentPage(),
                'lastPage' => $reviews->lastPage(),
                'perPage' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        );
    }

    public function store(ReviewStoreRequest $request, Product $product): JsonResponse
    {
        if (! $product->is_active) {
            return $this->notFound('Product not found');
        }

        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        try {
            $review = app(ProductReviewService::class)->createReview(
                $customer,
                $product,
                $request->validated(),
                $request->file('images', [])
            );
        } catch (ValidationException $exception) {
            return $this->error('Validation failed', 422, $exception->errors());
        }

        $review->loadMissing('customer');

        return $this->success(new ProductReviewResource($review), null, 201);
    }
}
