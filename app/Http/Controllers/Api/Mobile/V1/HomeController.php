<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\HomeResource;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\SiteSetting;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Models\StorefrontSetting;
use App\Models\Product;
use App\Domain\Products\Models\ProductImage;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Storefront\CampaignPlacementService;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HomeController extends ApiController
{
    public function index(HomeBuilderService $homeBuilder, Request $request): JsonResponse
    {
        $converter = app(CurrencyConversionService::class);
        $requestedCurrency = $request->header('X-Currency')
            ?? $request->query('currency')
            ?? $request->input('currency');
        $currency = $requestedCurrency ? $converter->normalize((string) $requestedCurrency) : 'USD';

        $locale = app()->getLocale();
        $sections = $homeBuilder->buildProductSections(6);
        $featured = $sections['featured'];
        $bestSellers = $sections['bestSellers'];
        $recommended = $sections['recommended'];

        $categories = $this->buildCategoryCards($homeBuilder);

        $homeContent = HomePageSetting::query()->latest()->first();
        $campaignPlacements = app(CampaignPlacementService::class);
        $campaignHeroBanners = $campaignPlacements->placementBanners('home_hero', $locale, 1);
        $campaignCarouselBanners = $campaignPlacements->placementBanners('home_carousel', $locale);
        $campaignStripBanners = $campaignPlacements->placementBanners('home_strip', $locale, 1);
        $seasonalDrops = $this->mapSeasonalDrops($locale, $homeBuilder);

        $heroBannerModels = $this->heroBannerModels();
        $hero = $this->mapHeroSlides($homeContent, $homeBuilder, $heroBannerModels);
        if (! empty($campaignHeroBanners)) {
            $hero = $this->mapHeroFromBannerPayloads($campaignHeroBanners);
        }

        $heroBanners = $this->mapBannerCollection($heroBannerModels, $homeBuilder);
        if (! empty($campaignHeroBanners)) {
            $heroBanners = $this->mapCampaignBannerPayloads($campaignHeroBanners);
        }

        $carouselBanners = $this->mapBannerCollection($this->carouselBannerModels(), $homeBuilder);
        if (! empty($campaignCarouselBanners)) {
            $carouselBanners = array_values(array_merge($this->mapCampaignBannerPayloads($campaignCarouselBanners), $carouselBanners));
        }

        $stripBanners = $this->mapBannerCollection($this->stripBannerModels(), $homeBuilder);
        if (! empty($campaignStripBanners)) {
            $stripBanners = $this->mapCampaignBannerPayloads($campaignStripBanners);
        }

        $fullBanners = $this->mapBannerCollection($this->fullBannerModels(), $homeBuilder);
        $popupBanners = $this->mapBannerCollection($this->popupBannerModels(), $homeBuilder);
        $storefront = $this->mapStorefrontSettings($homeBuilder);

        return $this->success(new HomeResource([
            'currency' => $currency,
            'hero' => $hero,
            'categories' => $categories,
            'flashDeals' => $featured,
            'trending' => $bestSellers,
            'recommended' => $recommended,
            'topStrip' => $this->mapTopStrip($homeContent),
            'valueProps' => $this->defaultValueProps(),
            'seasonalDrops' => $seasonalDrops,
            'banners' => [
                'hero' => $heroBanners,
                'carousel' => $carouselBanners,
                'strip' => $stripBanners,
                'full' => $fullBanners,
                'popup' => $popupBanners,
            ],
            'newsletterPopup' => $this->mapNewsletterPopup($homeBuilder),
            'storefront' => $storefront,
        ]));
    }

    private function buildCategoryCards(HomeBuilderService $homeBuilder): array
    {
        $locale = app()->getLocale();

        return Cache::remember("home:categories:{$locale}", now()->addMinutes(10), function () use ($homeBuilder, $locale) {
            $childIdsSubquery = Category::query()
                ->select('id')
                ->whereColumn('parent_id', 'categories.id');

            $productsCountSubquery = Product::query()
                ->selectRaw('COUNT(*)')
                ->where('is_active', true)
                ->where(function ($query) use ($childIdsSubquery) {
                    $query->whereColumn('category_id', 'categories.id')
                        ->orWhereIn('category_id', $childIdsSubquery);
                });

            $parents = Category::query()
                ->select('categories.*')
                ->addSelect(['products_count' => $productsCountSubquery])
                ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->having('products_count', '>', 0)
                ->orderByDesc('products_count')
                ->orderByDesc('updated_at')
                ->take(10)
                ->get();

            if ($parents->isEmpty()) {
                return [];
            }

            $children = Category::query()
                ->whereIn('parent_id', $parents->pluck('id'))
                ->where('is_active', true)
                ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
                ->orderByDesc('view_count')
                ->orderByDesc('updated_at')
                ->get()
                ->groupBy('parent_id');

            $childIds = $children->flatten()->pluck('id')->unique();
            $latestProductIdsByCategory = $childIds->isEmpty()
                ? collect()
                : Product::query()
                    ->whereIn('category_id', $childIds)
                    ->where('is_active', true)
                    ->selectRaw('MAX(id) as id, category_id')
                    ->groupBy('category_id')
                    ->pluck('id', 'category_id');

            $productImages = $latestProductIdsByCategory->isEmpty()
                ? collect()
                : ProductImage::query()
                    ->whereIn('product_id', $latestProductIdsByCategory->values())
                    ->orderBy('position')
                    ->get()
                    ->groupBy('product_id');

            $previewLookup = $parents->mapWithKeys(function (Category $parent) use ($children, $homeBuilder, $latestProductIdsByCategory, $productImages, $locale) {
                $previews = $children->get($parent->id, collect())
                    ->take(4)
                    ->map(function (Category $child) use ($homeBuilder, $latestProductIdsByCategory, $productImages, $locale) {
                        $image = $homeBuilder->normalizeImage($child->hero_image);
                        if (! $image) {
                            $productId = $latestProductIdsByCategory->get($child->id);
                            $image = $productId
                                ? $homeBuilder->normalizeImage(optional($productImages->get($productId))->first()?->url)
                                : null;
                        }

                        return [
                            'id' => $child->id,
                            'name' => $child->translatedValue('name', $locale),
                            'slug' => $child->slug,
                            'image_url' => $image,
                        ];
                    })
                    ->values()
                    ->all();

                return [$parent->id => $previews];
            });

            return $parents
                ->map(fn (Category $category, int $index) => $this->mapCategoryCard($category, $index, $homeBuilder, $previewLookup->get($category->id, [])))
                ->values()
                ->all();
        });
    }

    private function mapCategoryCard(Category $category, int $index, HomeBuilderService $homeBuilder, array $subcategoryPreviews = []): array
    {
        $image = $homeBuilder->normalizeImage($category->hero_image);
        $locale = app()->getLocale();

        return [
            'id' => $category->id,
            'name' => $category->translatedValue('name', $locale),
            'slug' => $category->slug,
            'count' => $category->products_count ?? 0,
            'product_count' => (int) ($category->products_count ?? 0),
            'image' => $image,
            'heroImage' => $image,
            'accent' => $this->accentForIndex($category->id ?: $index),
            'subcategory_previews' => $subcategoryPreviews,
        ];
    }

    private function mapHeroSlides(?HomePageSetting $homeContent, HomeBuilderService $homeBuilder, ?Collection $fallbackBanners = null): array
    {
        $slides = $homeContent?->hero_slides ?? [];
        if (! is_array($slides) || empty($slides)) {
            return $this->mapHeroFromBanners($homeBuilder, $fallbackBanners);
        }

        return collect($slides)->map(function (array $slide, int $index) use ($homeBuilder) {
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

    private function mapHeroFromBanners(HomeBuilderService $homeBuilder, ?Collection $banners = null): array
    {
        $banners = $banners ?? $this->heroBannerModels();

        return $banners->map(function (StorefrontBanner $banner, int $index) use ($homeBuilder) {
            return [
                'id' => $banner->id,
                'kicker' => $banner->badge_text ?? 'Featured',
                'title' => $banner->title ?? 'Shop now',
                'subtitle' => $banner->description ?? '',
                'cta' => $banner->cta_text ?? 'Shop now',
                'href' => $banner->getCtaUrl(),
                'image' => $this->resolveBannerImage($banner, $homeBuilder),
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

    private function heroBannerModels(): Collection
    {
        return StorefrontBanner::active()
            ->byDisplayType('hero')
            ->where(function ($query) {
                $query->where('target_type', 'none')
                    ->orWhereNull('target_type');
            })
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get();
    }

    private function carouselBannerModels(): Collection
    {
        return StorefrontBanner::active()
            ->byDisplayType('carousel')
            ->where(function ($query) {
                $query->where('target_type', 'none')
                    ->orWhereNull('target_type');
            })
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get();
    }

    private function stripBannerModels(): Collection
    {
        return StorefrontBanner::active()
            ->byDisplayType('strip')
            ->where(function ($query) {
                $query->where('target_type', 'none')
                    ->orWhereNull('target_type');
            })
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get();
    }

    private function fullBannerModels(): Collection
    {
        return StorefrontBanner::active()
            ->whereIn('display_type', ['full', 'full_image'])
            ->where(function ($query) {
                $query->where('target_type', 'none')
                    ->orWhereNull('target_type');
            })
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get();
    }

    private function popupBannerModels(): Collection
    {
        return StorefrontBanner::active()
            ->byDisplayType('popup')
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get();
    }

    private function mapBannerCollection(Collection $banners, HomeBuilderService $homeBuilder): array
    {
        return $banners->map(function (StorefrontBanner $banner) use ($homeBuilder) {
            $targeting = is_array($banner->targeting ?? null) ? $banner->targeting : [];

            return [
                'id' => (string) $banner->id,
                'title' => $banner->title,
                'description' => $banner->description,
                'type' => $banner->type,
                'displayType' => $banner->display_type,
                'image' => $this->resolveBannerImage($banner, $homeBuilder),
                'imageMode' => $targeting['image_mode'] ?? 'cover',
                'backgroundColor' => $banner->background_color,
                'textColor' => $banner->text_color,
                'badgeText' => $banner->badge_text,
                'badgeColor' => $banner->badge_color,
                'ctaText' => $banner->cta_text,
                'ctaUrl' => $banner->getCtaUrl(),
                'endsAt' => $banner->ends_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    private function mapCampaignBannerPayloads(array $banners): array
    {
        return collect($banners)->map(function (array $banner, int $index) {
            return [
                'id' => (string) ($banner['id'] ?? 'campaign-' . $index),
                'title' => $banner['title'] ?? null,
                'description' => $banner['description'] ?? null,
                'type' => $banner['type'] ?? 'campaign',
                'displayType' => $banner['displayType'] ?? 'hero',
                'image' => $banner['imagePath'] ?? null,
                'imageMode' => $banner['imageMode'] ?? null,
                'backgroundColor' => $banner['backgroundColor'] ?? null,
                'textColor' => $banner['textColor'] ?? null,
                'badgeText' => $banner['badgeText'] ?? null,
                'badgeColor' => $banner['badgeColor'] ?? null,
                'ctaText' => $banner['ctaText'] ?? null,
                'ctaUrl' => $banner['ctaUrl'] ?? null,
                'endsAt' => null,
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

    private function mapNewsletterPopup(HomeBuilderService $homeBuilder): ?array
    {
        $settings = StorefrontSetting::query()->latest()->first();
        if (! $settings?->newsletter_popup_enabled) {
            return null;
        }

        return [
            'enabled' => true,
            'title' => $settings->newsletter_popup_title,
            'body' => $settings->newsletter_popup_body,
            'incentive' => $settings->newsletter_popup_incentive,
            'image' => $homeBuilder->normalizeImage($settings->newsletter_popup_image),
            'delaySeconds' => $settings->newsletter_popup_delay_seconds,
            'dismissDays' => $settings->newsletter_popup_dismiss_days,
            'source' => 'mobile_popup',
        ];
    }

    private function resolveBannerImage(StorefrontBanner $banner, HomeBuilderService $homeBuilder): ?string
    {
        if ($banner->image_path) {
            return $homeBuilder->normalizeImage($banner->image_path);
        }

        if ($banner->target_type === 'product' && $banner->product) {
            $image = $banner->product->images?->first()?->url ?? null;
            return $homeBuilder->normalizeImage($image);
        }

        if ($banner->target_type === 'category' && $banner->category) {
            return $homeBuilder->normalizeImage($banner->category->hero_image ?? null);
        }

        return null;
    }

    private function mapStorefrontSettings(HomeBuilderService $homeBuilder): array
    {
        $storefront = StorefrontSetting::query()->latest()->first();
        $site = SiteSetting::query()->latest()->first();

        return [
            'brandName' => $storefront?->brand_name ?? $site?->site_name ?? config('app.name', 'Store'),
            'logo' => $this->resolveLogoPath($site, $homeBuilder),
        ];
    }

    private function resolveLogoPath(?SiteSetting $site, HomeBuilderService $homeBuilder): ?string
    {
        $logo = $site?->logo_path ?? null;
        if (is_array($logo)) {
            $logo = $logo['path'] ?? $logo['url'] ?? $logo[0] ?? null;
        }
        if (is_string($logo) && $logo !== '') {
            return $homeBuilder->normalizeImage($logo);
        }
        return null;
    }

    private function defaultValueProps(): array
    {
        return [
            [
                'title' => 'Fast dispatch',
                'body' => 'Suppliers confirm within 24-48 hours.',
            ],
            [
                'title' => 'Easy returns',
                'body' => 'Refunds if your item has issues.',
            ],
            [
                'title' => 'Live support',
                'body' => 'Chat with us 24/7 for order help.',
            ],
        ];
    }

    private function toneForIndex(int $index): string
    {
        return ['#1f2937', '#111827', '#0f172a', '#1e1b4b'][$index % 4];
    }

    private function accentForIndex(int $index): string
    {
        return ['#f97316', '#0ea5e9', '#22c55e', '#f59e0b'][$index % 4];
    }
}
