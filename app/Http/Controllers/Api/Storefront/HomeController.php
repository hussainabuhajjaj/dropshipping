<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    use TransformsProducts;
    public function __invoke(): JsonResponse
    {
        // NOTE: Home page data assembly is duplicated in Storefront\\HomeController.
        // Keep in sync if you change sections, ordering, or limits.
        $baseQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $featured = (clone $baseQuery)
            ->where('is_featured', true)
            ->latest()
            ->take(6)
            ->get();

        if ($featured->isEmpty()) {
            $featured = (clone $baseQuery)
                ->latest()
                ->take(6)
                ->get();
        }

        $bestSellerIds = $this->topSellingProductIds();
        $bestSellersQuery = (clone $baseQuery);
        if (! empty($bestSellerIds)) {
            $bestSellersQuery
                ->whereIn('products.id', $bestSellerIds)
                ->orderByRaw('FIELD(products.id, ' . implode(',', $bestSellerIds) . ')');
        } else {
            $bestSellersQuery->orderByDesc('selling_price');
        }
        $bestSellers = $bestSellersQuery
            ->take(6)
            ->get();

        $recommendedQuery = clone $baseQuery;
        if ($featured->isNotEmpty()) {
            $recommendedQuery->whereNotIn('id', $featured->pluck('id'));
        }
        $recommended = $recommendedQuery
            ->inRandomOrder()
            ->take(6)
            ->get();

        $categories = Category::query()
            ->withCount(['products as products_count' => fn ($q) => $q->where('is_active', true)])
            ->with(['translations' => fn ($q) => $q->where('locale', app()->getLocale())])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('view_count')
            ->orderByDesc('products_count')
            ->take(8)
            ->get()
            ->map(fn (Category $category, int $index) => $this->mapCategoryCard($category, $index))
            ->values()
            ->all();

        $homeContent = HomePageSetting::query()->latest()->first();

        return response()->json([
            'currency' => 'USD',
            'hero' => $this->mapHeroSlides($homeContent),
            'categories' => $categories,
            'flashDeals' => $featured->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'trending' => $bestSellers->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'recommended' => $recommended->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'topStrip' => $this->mapTopStrip($homeContent),
            'valueProps' => $this->defaultValueProps(),
        ]);
    }

    private function mapCategoryCard(Category $category, int $index): array
    {
        $image = $this->formatImage($category->hero_image);
        $locale = app()->getLocale();

        return [
            'id' => $category->id,
            'name' => $category->translatedValue('name', $locale),
            'slug' => $category->slug,
            'count' => $category->products_count ?? 0,
            'image' => $image,
            'accent' => $this->accentForIndex($category->id ?: $index),
        ];
    }

    private function mapHeroSlides(?HomePageSetting $homeContent): array
    {
        $slides = $homeContent?->hero_slides ?? [];
        if (! is_array($slides) || empty($slides)) {
            return $this->mapHeroFromBanners();
        }

        return collect($slides)->map(function (array $slide, int $index) {
            $image = $this->formatImage($slide['image'] ?? null);

            return [
                'id' => $slide['id'] ?? 'hero-' . $index,
                'kicker' => $slide['kicker'] ?? 'Featured',
                'title' => $slide['title'] ?? '',
                'subtitle' => $slide['subtitle'] ?? '',
                'cta' => $slide['primary_label'] ?? 'Shop now',
                'href' => $slide['primary_href'] ?? '/products',
                'image' => $image,
                'tone' => $this->toneForIndex($index),
            ];
        })->values()->all();
    }

    private function mapHeroFromBanners(): array
    {
        $banners = StorefrontBanner::active()
            ->byDisplayType('hero')
            ->where(function ($query) {
                $query->where('target_type', 'none')
                    ->orWhereNull('target_type');
            })
            ->orderBy('display_order')
            ->get();

        return $banners->map(function (StorefrontBanner $banner, int $index) {
            return [
                'id' => $banner->id,
                'kicker' => $banner->badge_text ?? 'Featured',
                'title' => $banner->title ?? 'Shop now',
                'subtitle' => $banner->description ?? '',
                'cta' => $banner->cta_text ?? 'Shop now',
                'href' => $banner->getCtaUrl(),
                'image' => $banner->image_path ? Storage::url($banner->image_path) : null,
                'tone' => $banner->background_color ?? $this->toneForIndex($index),
            ];
        })->values()->all();
    }

    private function mapTopStrip(?HomePageSetting $homeContent): array
    {
        $strip = $homeContent?->top_strip ?? [];
        if (! is_array($strip) || empty($strip)) {
            return [
                ['icon' => 'zap', 'title' => 'Flash deals daily', 'subtitle' => 'New drops every 24h.'],
                ['icon' => 'check-circle', 'title' => 'Verified stock', 'subtitle' => 'We check suppliers for you.'],
                ['icon' => 'truck', 'title' => 'Fast delivery', 'subtitle' => 'Clear ETAs at checkout.'],
            ];
        }

        return collect($strip)->map(fn (array $item) => [
            'icon' => (string) ($item['icon'] ?? 'zap'),
            'title' => $item['title'] ?? 'Flash deals daily',
            'subtitle' => $item['subtitle'] ?? 'New drops every 24h.',
        ])->values()->all();
    }

    private function defaultValueProps(): array
    {
        return [
            [
                'title' => 'Fast dispatch',
                'body' => 'Suppliers confirm within 24-48 hours.',
            ],
            [
                'title' => 'Customs clarity',
                'body' => 'Duties shown before checkout.',
            ],
            [
                'title' => 'Live tracking',
                'body' => 'Delivery updates inside the app.',
            ],
        ];
    }

    private function formatImage(?string $image): ?string
    {
        // NOTE: Image URL normalization is duplicated in Storefront HomeController
        // and HandleInertiaRequests. Keep in sync if URL handling changes.
        if (! $image) {
            return null;
        }

        if (! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
            return Storage::url($image);
        }

        return $image;
    }

    private function accentForIndex(int $seed): string
    {
        $palette = [
            '#ffe9cc',
            '#dbe8ff',
            '#ffe0f4',
            '#f0ffe8',
            '#e9fff5',
            '#fef4dd',
        ];

        $index = abs($seed) % count($palette);

        return $palette[$index];
    }

    private function toneForIndex(int $seed): string
    {
        $tones = [
            '#ffe7d6',
            '#fff6c8',
            '#f8d8ff',
            '#e6f7ff',
        ];

        $index = abs($seed) % count($tones);

        return $tones[$index];
    }

    private function topSellingProductIds(): array
    {
        return Cache::remember('home:top-selling-product-ids', now()->addMinutes(8), function () {
            return OrderItem::query()
                ->select('product_variants.product_id', DB::raw('SUM(order_items.quantity) as units'))
                ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->groupBy('product_variants.product_id')
                ->orderByDesc('units')
                ->limit(6)
                ->pluck('product_variants.product_id')
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();
        });
    }
}
