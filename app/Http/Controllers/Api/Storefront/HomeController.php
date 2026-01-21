<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\Product;
use App\Models\StorefrontBanner;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    use TransformsProducts;
    public function __invoke(HomeBuilderService $homeBuilder): JsonResponse
    {
        // NOTE: Home page data assembly is duplicated in Storefront\\HomeController.
        // Keep in sync if you change sections, ordering, or limits.
        $sections = $homeBuilder->buildProductSections(6);
        $featured = $sections['featured'];
        $bestSellers = $sections['bestSellers'];
        $recommended = $sections['recommended'];

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
            ->map(fn (Category $category, int $index) => $this->mapCategoryCard($category, $index, $homeBuilder))
            ->values()
            ->all();

        $homeContent = HomePageSetting::query()->latest()->first();

        return response()->json([
            'currency' => 'USD',
            'hero' => $this->mapHeroSlides($homeContent, $homeBuilder),
            'categories' => $categories,
            'flashDeals' => $featured->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'trending' => $bestSellers->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'recommended' => $recommended->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'topStrip' => $this->mapTopStrip($homeContent),
            'valueProps' => $this->defaultValueProps(),
        ]);
    }

    private function mapCategoryCard(Category $category, int $index, HomeBuilderService $homeBuilder): array
    {
        $image = $homeBuilder->normalizeImage($category->hero_image);
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

    private function mapHeroSlides(?HomePageSetting $homeContent, HomeBuilderService $homeBuilder): array
    {
        $slides = $homeContent?->hero_slides ?? [];
        if (! is_array($slides) || empty($slides)) {
            return $this->mapHeroFromBanners($homeBuilder);
        }

        return collect($slides)->map(function (array $slide, int $index) {
            $image = $homeBuilder->normalizeImage($slide['image'] ?? null);

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

    private function mapHeroFromBanners(HomeBuilderService $homeBuilder): array
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
                'image' => $homeBuilder->normalizeImage($banner->image_path),
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

}
