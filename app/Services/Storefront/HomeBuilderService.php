<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HomeBuilderService
{
    public function baseProductQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');
    }

    /**
     * @return array{featured:\Illuminate\Support\Collection,bestSellers:\Illuminate\Support\Collection,recommended:\Illuminate\Support\Collection}
     */
    public function buildProductSections(int $limit = 6): array
    {
        $baseQuery = $this->baseProductQuery();

        $featured = (clone $baseQuery)
            ->where('is_featured', true)
            ->latest()
            ->take($limit)
            ->get();

        if ($featured->isEmpty()) {
            $featured = (clone $baseQuery)
                ->latest()
                ->take($limit)
                ->get();
        }

        $bestSellerIds = $this->topSellingProductIds($limit);
        $bestSellersQuery = (clone $baseQuery);
        if (! empty($bestSellerIds)) {
            $bestSellersQuery
                ->whereIn('products.id', $bestSellerIds)
                ->orderByRaw('FIELD(products.id, ' . implode(',', $bestSellerIds) . ')');
        } else {
            $bestSellersQuery->orderByDesc('selling_price');
        }

        $bestSellers = $bestSellersQuery
            ->take($limit)
            ->get();

        $recommendedQuery = clone $baseQuery;
        if ($featured->isNotEmpty()) {
            $recommendedQuery->whereNotIn('id', $featured->pluck('id'));
        }

        $recommended = $recommendedQuery
            ->inRandomOrder()
            ->take($limit)
            ->get();

        return [
            'featured' => $featured,
            'bestSellers' => $bestSellers,
            'recommended' => $recommended,
        ];
    }

    public function topSellingProductIds(int $limit = 6): array
    {
        return Cache::remember('home:top-selling-product-ids', now()->addMinutes(8), function () use ($limit) {
            return OrderItem::query()
                ->select('product_variants.product_id', DB::raw('SUM(order_items.quantity) as units'))
                ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->groupBy('product_variants.product_id')
                ->orderByDesc('units')
                ->limit($limit)
                ->pluck('product_variants.product_id')
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();
        });
    }

    public function normalizeImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    }
}
