<?php

declare(strict_types=1);

namespace App\Repositories\Api;

use App\Contracts\Catalog\ProductCatalogContract;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductCatalogContract
{
    public function listProducts(array $filters, int $perPage = 18): LengthAwarePaginator
    {
        $locale = app()->getLocale();

        $query = Product::query()
            ->where('is_active', true)
            ->with([
                'images',
                'category',
                'category.translations' => fn ($q) => $q->where('locale', $locale),
                'variants',
                'translations' => fn ($q) => $q->where('locale', $locale),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if (! empty($filters['category_id'])) {
            $ids = is_array($filters['category_id']) ? $filters['category_id'] : [$filters['category_id']];
            $query->whereIn('category_id', $ids);
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(fn ($b) => $b
                ->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            );
        }

        if (! empty($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('selling_price', '>=', (float) $filters['min_price']);
        }

        if (! empty($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('selling_price', '<=', (float) $filters['max_price']);
        }

        if (! empty($filters['rating']) && is_numeric($filters['rating'])) {
            $query->having('reviews_avg_rating', '>=', (float) $filters['rating']);
        }

        if (! empty($filters['in_stock'])) {
            $query->where('stock_on_hand', '>', 0);
        }

        $sort = $filters['sort'] ?? 'newest';
        $sortable = [
            'newest'     => ['created_at', 'desc'],
            'price_asc'  => ['selling_price', 'asc'],
            'price_desc' => ['selling_price', 'desc'],
            'rating'     => ['reviews_avg_rating', 'desc'],
            'popularity' => ['reviews_count', 'desc'],
        ];

        if (isset($sortable[$sort])) {
            [$field, $direction] = $sortable[$sort];
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?object
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->find($id);
    }

    public function findBySlug(string $slug): ?object
    {
        return Product::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->first();
    }

    public function getCategoryDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = \App\Domain\Products\Models\Category::query()
            ->where('parent_id', $categoryId)
            ->pluck('id')
            ->all();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->getCategoryDescendantIds($childId));
        }

        return array_values(array_unique($ids));
    }
}
