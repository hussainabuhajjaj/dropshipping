<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\Product;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Services\Storefront\CampaignPlacementService;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    use TransformsProducts;
    public function __invoke(HomeBuilderService $homeBuilder): JsonResponse
    {
        // NOTE: Home page data assembly is duplicated in Storefront\\HomeController.
        // Keep in sync if you change sections, ordering, or limits.
        $locale = app()->getLocale();
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
        $campaignPlacements = app(CampaignPlacementService::class);
        $campaignHeroBanners = $campaignPlacements->placementBanners('home_hero', $locale, 1);
        $seasonalDrops = $this->mapSeasonalDrops($locale, $homeBuilder);

        $hero = $this->mapHeroSlides($homeContent, $homeBuilder);
        if (! empty($campaignHeroBanners)) {
            $hero = $this->mapHeroFromBannerPayloads($campaignHeroBanners);
        }

        return response()->json([
            'currency' => 'USD',
            'hero' => $hero,
            'categories' => $categories,
            'flashDeals' => $featured->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'trending' => $bestSellers->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'recommended' => $recommended->map(fn (Product $product) => $this->transformProduct($product))->values()->all(),
            'topStrip' => $this->mapTopStrip($homeContent),
            'valueProps' => $this->defaultValueProps(),
            'seasonalDrops' => $seasonalDrops,
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

    private function mapHeroFromBannerPayloads(array $banners): array
    {
        return collect($banners)->map(function (array $banner, int $index) {
            return [
                'id' => $banner['id'] ?? 'campaign-' . $index,
                'kicker' => $banner['badgeText'] ?? 'Featured',
                'title' => $banner['title'] ?? 'Shop now',
                'subtitle' => $banner['description'] ?? '',
                'cta' => $banner['ctaText'] ?? 'Shop now',
                'href' => $banner['ctaUrl'] ?? '/products',
                'image' => $banner['imagePath'] ?? null,
                'tone' => $banner['backgroundColor'] ?? $this->toneForIndex($index),
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

    private function mapSeasonalDrops(string $locale, HomeBuilderService $homeBuilder): array
    {
        $campaigns = StorefrontCampaign::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (StorefrontCampaign $campaign) => $campaign->isActiveForLocale($locale))
            ->filter(fn (StorefrontCampaign $campaign) => in_array($campaign->type, ['seasonal', 'drop', 'event'], true))
            ->take(4);

        $collections = StorefrontCollection::query()
            ->orderBy('display_order')
            ->get()
            ->filter(fn (StorefrontCollection $collection) => $collection->isActiveForLocale($locale))
            ->filter(fn (StorefrontCollection $collection) => in_array($collection->type, ['seasonal', 'drop', 'guide', 'collection'], true))
            ->take(6);

        $items = [];

        foreach ($campaigns as $campaign) {
            $items[] = [
                'id' => 'campaign-' . $campaign->id,
                'kind' => 'campaign',
                'kicker' => $campaign->localizedValue('hero_kicker', $locale) ?? strtoupper($campaign->type),
                'title' => $campaign->localizedValue('name', $locale) ?? $campaign->name,
                'subtitle' => $campaign->localizedValue('hero_subtitle', $locale) ?? '',
                'image' => $homeBuilder->normalizeImage($campaign->hero_image),
                'href' => '/campaigns/' . $campaign->slug,
                'tag' => $campaign->stacking_mode === 'exclusive' ? 'Exclusive' : 'Drop',
            ];
        }

        foreach ($collections as $collection) {
            $items[] = [
                'id' => 'collection-' . $collection->id,
                'kind' => 'collection',
                'kicker' => $collection->localizedValue('hero_kicker', $locale) ?? strtoupper($collection->type),
                'title' => $collection->localizedValue('title', $locale) ?? $collection->title,
                'subtitle' => $collection->localizedValue('description', $locale) ?? '',
                'image' => $homeBuilder->normalizeImage($collection->hero_image),
                'href' => '/collections/' . $collection->slug,
                'tag' => $collection->type === 'guide' ? 'Guide' : 'Collection',
            ];
        }

        return array_slice($items, 0, 6);
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
