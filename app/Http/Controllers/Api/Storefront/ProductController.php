<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Storefront\Product\ProductIndexRequest;
use App\Http\Resources\Storefront\ProductDetailResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = $validated['category'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $query = $validated['q'] ?? null;

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($category) {
            $productQuery->whereHas('category', function ($builder) use ($category) {
                $builder->where('name', $category)->orWhere('slug', $category);
            });
        }

        $minValue = $minPrice !== null && is_numeric($minPrice) ? (float) $minPrice : null;
        $maxValue = $maxPrice !== null && is_numeric($maxPrice) ? (float) $maxPrice : null;
        $productQuery->priceRange($minValue, $maxValue);

        if ($query) {
            $productQuery->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
                $builder->orWhereHas('category', function ($categoryBuilder) use ($query) {
                    $categoryBuilder->where('name', 'like', '%' . $query . '%');
                });
            });
        }

        $perPage = min((int) ($validated['per_page'] ?? 18), 50);
        $products = (match ($validated['sort'] ?? 'newest') {
            'price_asc' => $productQuery
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) asc'),
            'price_desc' => $productQuery
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) desc'),
            'rating' => $productQuery->orderByDesc('reviews_avg_rating'),
            'popular' => $productQuery->orderByDesc('reviews_count'),
            default => $productQuery->latest(),
        })->paginate($perPage);

        return response()->json([
            'products' => ProductResource::collection($products->getCollection()),
            'pagination' => [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_if(! $product->is_active, 404);

        $product->load(['images', 'variants', 'category', 'translations']);

        return response()->json([
            'product' => new ProductDetailResource($product),
        ]);
    }
}
