<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Product\ProductIndexRequest;
use App\Http\Resources\Mobile\V1\ProductDetailResource;
use App\Http\Resources\Mobile\V1\ProductResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class ProductController extends ApiController
{
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = $validated['category'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $query = $validated['q'] ?? null;
        $sort = $validated['sort'] ?? 'newest';

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($category) {
            $locale = app()->getLocale();
            $categoryModel = Category::query()
                ->where('slug', $category)
                ->orWhere('name', $category)
                ->orWhereHas('translations', function ($builder) use ($category, $locale) {
                    $builder->where('locale', $locale)->where('name', $category);
                })
                ->first();

            if ($categoryModel) {
                $categoryIds = Category::query()
                    ->where('parent_id', $categoryModel->id)
                    ->pluck('id')
                    ->push($categoryModel->id)
                    ->unique()
                    ->values();

                $productQuery->whereIn('category_id', $categoryIds);
            } else {
                $productQuery->whereHas('category', function ($builder) use ($category) {
                    $builder->where('name', $category)->orWhere('slug', $category);
                });
            }
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
                    $categoryBuilder->where('name', 'like', '%' . $query . '%')
                        ->orWhereHas('translations', function ($translationBuilder) use ($query) {
                            $translationBuilder->where('name', 'like', '%' . $query . '%');
                        });
                });
            });
        }

        $productQuery = match ($sort) {
            'price_asc' => $productQuery
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) asc'),
            'price_desc' => $productQuery
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) desc'),
            'rating' => $productQuery->orderByDesc('reviews_avg_rating'),
            'popular' => $productQuery->orderByDesc('reviews_count'),
            default => $productQuery->latest(),
        };

        $perPage = min((int) ($validated['per_page'] ?? 18), 50);
        $products = $productQuery->paginate($perPage);

        return $this->success(
            ProductResource::collection($products->getCollection()),
            null,
            200,
            [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ]
        );
    }

    public function show(Product $product): JsonResponse
    {
        abort_if(! $product->is_active, 404);

        $product->load(['images', 'variants', 'category', 'translations']);

        return $this->success(new ProductDetailResource($product));
    }
}
