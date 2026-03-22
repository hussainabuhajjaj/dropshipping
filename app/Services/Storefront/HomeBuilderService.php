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
     * @return array{
     *     featured:\Illuminate\Support\Collection,
     *     bestSellers:\Illuminate\Support\Collection,
     *     recommended:\Illuminate\Support\Collection,
     *     newArrivals:\Illuminate\Support\Collection,
     *     trending:\Illuminate\Support\Collection,
     *     bestValue:\Illuminate\Support\Collection
     * }
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

        $newArrivals = (clone $baseQuery)
            ->latest()
            ->take($limit)
            ->get();

        $trending = (clone $baseQuery)
            ->orderByDesc('reviews_count')
            ->orderByDesc('reviews_avg_rating')
            ->latest()
            ->take($limit)
            ->get();

        $bestValue = (clone $baseQuery)
            ->where('selling_price', '>', 0)
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('selling_price')
            ->take($limit)
            ->get();

        if ($bestValue->isEmpty()) {
            $bestValue = $newArrivals->take($limit)->values();
        }

        return [
            'featured' => $featured,
            'bestSellers' => $bestSellers,
            'recommended' => $recommended,
            'newArrivals' => $newArrivals,
            'trending' => $trending,
            'bestValue' => $bestValue,
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

    public function normalizeImage(?string $image): ?string
    {
        // Return null/empty as null
        if ($image === null || $image === '') {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        // Check storage path first (where images actually exist based on diagnosis)
        $storagePath = storage_path('app/public/' . $image);
        if (file_exists($storagePath)) {
            return url('storage/' . $image);
        }

        // Check public storage path (alternative location)
        $publicStoragePath = public_path('storage/' . $image);
        if (file_exists($publicStoragePath)) {
            return url('storage/' . $image);
        }

        // Check direct public path (original logic)
        $publicPath = public_path($image);
        if (file_exists($publicPath)) {
            return url($image);
        }

        // If no file exists, return the path anyway (let browser handle 404)
        // This is better than returning default image for valid paths
        return url('storage/' . $image);
    }
}
