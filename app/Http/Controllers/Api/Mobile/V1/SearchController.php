<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Search\SearchIndexRequest;
use App\Http\Resources\Mobile\V1\SearchResultResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchController extends ApiController
{
    public function index(SearchIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = $validated['q'] ?? null;
        $category = $validated['category'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $sort = $validated['sort'] ?? 'newest';
        $perPage = min((int) ($validated['per_page'] ?? 18), 50);
        $categoriesLimit = min((int) ($validated['categories_limit'] ?? 6), 20);

        $productQuery = $this->buildProductQuery($query, $category, $minPrice, $maxPrice);
        $productQuery = $this->applyProductSort($productQuery, $sort);
        $products = $productQuery->paginate($perPage);

        $categoriesQuery = Category::query()
            ->active()
            ->withCount('products');

        if ($query) {
            $categoriesQuery->where(function (Builder $builder) use ($query) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('slug', 'like', '%' . $query . '%');
            });
        }

        $categories = $categoriesQuery
            ->orderByDesc('products_count')
            ->limit($categoriesLimit)
            ->get();

        return $this->success(
            new SearchResultResource([
                'query' => $query,
                'products' => $products->getCollection(),
                'categories' => $categories,
            ]),
            null,
            200,
            [
                'products' => [
                    'currentPage' => $products->currentPage(),
                    'lastPage' => $products->lastPage(),
                    'perPage' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'categories' => [
                    'total' => $categories->count(),
                ],
            ]
        );
    }

    private function buildProductQuery(
        ?string $query,
        ?string $category,
        mixed $minPrice,
        mixed $maxPrice
    ): Builder {
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

        return $productQuery;
    }

    private function applyProductSort(Builder $productQuery, string $sort): Builder
    {
        return match ($sort) {
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
    }
}
