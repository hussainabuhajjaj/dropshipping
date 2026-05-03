<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\Product;
use App\Models\StorefrontBanner;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Services\Promotions\PromotionHomepageService;
use App\Services\Storefront\CampaignPlacementService;
use App\Services\Storefront\HomeBuilderService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    private function pinnedCategoryPriority(Category $category, string $locale): int
    {
        $normalize = static function (?string $value): string {
            $value = strtolower((string) $value);
            $value = str_replace(["'", '’'], '', $value);

            return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
        };

        $name = $normalize(method_exists($category, 'translatedValue') ? $category->translatedValue('name', $locale) : $category->name);
        $slug = $normalize($category->slug);

        if (in_array($name, ['womens clothing', 'women clothing'], true) || in_array($slug, ['womens clothing', 'women clothing'], true)) {
            return 0;
        }

        if (in_array($name, ['mens clothing', 'men clothing'], true) || in_array($slug, ['mens clothing', 'men clothing'], true)) {
            return 1;
        }

        return 2;
    }

    public function index(PromotionHomepageService $promotionHomepageService, HomeBuilderService $homeBuilder): Response
    {
        $locale = app()->getLocale();
        $sections = $homeBuilder->buildProductSections(6);

        $featured = $sections['featured'] ?? collect();
        $bestSellers = $sections['bestSellers'] ?? collect();
        $recommended = $sections['recommended'] ?? collect();
        $bestValue = $sections['bestValue'] ?? collect();

        $categoryList = $this->rootCategoriesTree(['children', 'children.children']);
        $featuredCategories = $this->featuredCategoriesForHome($locale, $homeBuilder);

        $homeContent = HomePageSetting::latestForLocale($locale);
        $categoryHighlights = $this->resolveCategoryHighlights($homeContent);
        $featuredCategorySections = $this->buildFeaturedCategorySections($categoryHighlights, 8, 4);
        $heroSlides = $this->normalizeHeroSlides($homeContent, $homeBuilder);

        $homepagePromotions = $promotionHomepageService->getHomepagePromotions();
        $promotionBanners = $this->buildPromotionBanners($homepagePromotions);
        $bannerResolution = $this->resolveHomeBanners($locale, $promotionBanners);

        $seasonalDropsPayload = $this->buildSeasonalDrops($locale, $homeBuilder);
        $homeCollectionsPayload = $this->buildHomeCollections($locale, $homeBuilder);
        $flashDealsPayload = $this->buildFlashDeals($homepagePromotions);

        return Inertia::render('Home', [
            'featured' => $featured->map(fn (Product $product) => $this->transformProduct($product))->values(),
            'bestSellers' => $bestSellers->map(fn (Product $product) => $this->transformProduct($product))->values(),
            'recommended' => $recommended->map(fn (Product $product) => $this->transformProduct($product))->values(),
            'bestValue' => $bestValue->map(fn (Product $product) => $this->transformProduct($product))->values(),
            'flashDeals' => $flashDealsPayload['items'],
            'flashDealsViewAllHref' => $flashDealsPayload['viewAllHref'],
            'categories' => $categoryList,
            'featuredCategories' => $featuredCategories,
            'categoryHighlights' => $categoryHighlights,
            'featuredCategorySections' => $featuredCategorySections,
            'currency' => 'USD',
            'banners' => $bannerResolution['banners'],
            'bannerDiagnostics' => $bannerResolution['diagnostics'],
            'seasonalDrops' => $seasonalDropsPayload['items'],
            'seasonalDropsViewAllHref' => $seasonalDropsPayload['viewAllHref'],
            'homeCollections' => $homeCollectionsPayload['items'],
            'homeCollectionsViewAllHref' => $homeCollectionsPayload['viewAllHref'],
            'popularSearches' => collect(Cache::get('popular_searches', collect()))
                ->take(10)
                ->map(fn ($count, $query) => [
                    'query' => (string) $query,
                    'count' => (int) $count,
                    'href' => '/search?q=' . urlencode((string) $query),
                ])
                ->values()
                ->all(),
            'homeContent' => $homeContent ? [
                'top_strip' => $homeContent->top_strip,
                'hero_slides' => $heroSlides,
                'rail_cards' => $homeContent->rail_cards,
                'banner_strip' => $homeContent->banner_strip,
            ] : null,
            'homepagePromotions' => $homepagePromotions,
        ]);
    }

    private function normalizeHeroSlides(?HomePageSetting $homeContent, HomeBuilderService $homeBuilder): array
    {
        $heroSlides = $homeContent?->hero_slides ?? [];
        if (! is_array($heroSlides)) {
            return [];
        }

        return collect($heroSlides)
            ->filter(fn ($slide) => is_array($slide))
            ->map(function (array $slide) use ($homeBuilder) {
                $image = $slide['image'] ?? null;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $slide['image'] = $homeBuilder->normalizeImage($image);
                }

                return $slide;
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $promotionBanners
     * @return array{banners: array<string, mixed>, diagnostics: array<string, mixed>}
     */
    private function resolveHomeBanners(string $locale, array $promotionBanners): array
    {
        $campaignPlacements = app(CampaignPlacementService::class);

        $campaignHero = $campaignPlacements->placementBanners('home_hero', $locale);
        $campaignCarousel = $campaignPlacements->placementBanners('home_carousel', $locale);
        $campaignStrip = $campaignPlacements->placementBanners('home_strip', $locale);

        $baseHero = $this->baseBannersForDisplayType('hero');
        $baseCarousel = $this->baseBannersForDisplayType('carousel');
        $baseStrip = $this->baseBannersForDisplayType('strip');

        $heroResolution = $this->mergeBannerSources([
            ['source' => 'campaign', 'items' => $campaignHero],
            ['source' => 'banner', 'items' => $baseHero],
        ], 1);

        $carouselResolution = $this->mergeBannerSources([
            ['source' => 'campaign', 'items' => $campaignCarousel],
            ['source' => 'banner', 'items' => $baseCarousel],
            ['source' => 'promotion', 'items' => $promotionBanners],
        ]);

        $stripResolution = $this->mergeBannerSources([
            ['source' => 'campaign', 'items' => $campaignStrip],
            ['source' => 'banner', 'items' => $baseStrip],
        ], 1);

        return [
            'banners' => [
                'hero' => $heroResolution['selected'],
                'carousel' => $carouselResolution['selected'],
                'strip' => $stripResolution['selected'][0] ?? null,
            ],
            'diagnostics' => [
                'generated_at' => now()->toIso8601String(),
                'hero' => $heroResolution['diagnostics'],
                'carousel' => $carouselResolution['diagnostics'],
                'strip' => $stripResolution['diagnostics'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baseBannersForDisplayType(string $displayType): array
    {
        return StorefrontBanner::active()
            ->byDisplayType($displayType)
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->get()
            ->map(fn (StorefrontBanner $banner) => $this->transformBanner($banner))
            ->values()
            ->all();
    }

    /**
     * Featured categories list for homepage rails.
     *
     * @return array<int, array<string, mixed>>
     */
    private function featuredCategoriesForHome(string $locale, HomeBuilderService $homeBuilder): array
    {
        $flagColumn = Schema::hasColumn('categories', 'is_featured')
            ? 'is_featured'
            : (Schema::hasColumn('categories', 'is_feature') ? 'is_feature' : null);

        if (! $flagColumn) {
            return [];
        }

        $idSignature = Category::query()
            ->where($flagColumn, true)
            ->orderBy('id')
            ->pluck('id')
            ->implode(',');

        $cacheKey = 'home:featured-categories:' . $locale . ':' . md5($idSignature);

        return Cache::remember($cacheKey, now()->addMinutes(20), function () use ($locale, $homeBuilder) {
            $flagColumn = Schema::hasColumn('categories', 'is_featured')
                ? 'is_featured'
                : (Schema::hasColumn('categories', 'is_feature') ? 'is_feature' : null);

            $query = Category::query()
                ->where('is_active', true)
                ->where($flagColumn, true)
                ->select(['id', 'name', 'slug', 'hero_image', 'parent_id', 'created_at', $flagColumn . ' as is_featured'])
                ->with(['translations' => fn ($q) => $q->where('locale', $locale)]);

            if (Schema::hasColumn('categories', 'featured_order')) {
                $query->orderByRaw('featured_order is null')->orderBy('featured_order');
            }

            $query->orderBy('created_at')->orderBy('name');

            return $query->get()
                ->sortBy(fn (Category $category) => [
                    $this->pinnedCategoryPriority($category, $locale),
                    (int) ($category->featured_order ?? PHP_INT_MAX),
                    optional($category->created_at)?->getTimestamp() ?? 0,
                    method_exists($category, 'translatedValue')
                        ? $category->translatedValue('name', $locale)
                        : $category->name,
                ])
                ->values()
                ->map(function (Category $category) use ($locale, $homeBuilder) {
                    $name = method_exists($category, 'translatedValue')
                        ? $category->translatedValue('name', $locale)
                        : $category->name;

                    return [
                    'id' => $category->id,
                    'name' => $name,
                    'slug' => $category->slug,
                    'image' => $homeBuilder->normalizeImage($category->hero_image),
                    'parent_id' => $category->parent_id,
                    'is_featured' => (bool) $category->is_featured,
                    ];
                })->all();
        });
    }

    /**
     * @param array<int, array{source:string,items:array<int,array<string,mixed>>}> $sources
     * @return array{selected: array<int, array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    private function mergeBannerSources(array $sources, int $limit = 0): array
    {
        $selected = [];
        $hidden = [];
        $seen = [];

        foreach ($sources as $sourcePayload) {
            $source = (string) ($sourcePayload['source'] ?? 'unknown');
            $items = is_array($sourcePayload['items'] ?? null) ? $sourcePayload['items'] : [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? 'unknown-' . count($selected));
                $ctaUrl = $this->normalizeStorefrontUrl($item['ctaUrl'] ?? null);

                if ($ctaUrl === null) {
                    $hidden[] = ['id' => $id, 'source' => $source, 'reason' => 'missing_or_invalid_cta'];
                    continue;
                }

                if (isset($seen[$id])) {
                    $hidden[] = ['id' => $id, 'source' => $source, 'reason' => 'duplicate_banner_id'];
                    continue;
                }

                if ($limit > 0 && count($selected) >= $limit) {
                    $hidden[] = ['id' => $id, 'source' => $source, 'reason' => 'lower_priority_than_selected'];
                    continue;
                }

                $item['ctaUrl'] = $ctaUrl;
                $item['source'] = $source;

                $selected[] = $item;
                $seen[$id] = true;
            }
        }

        return [
            'selected' => $selected,
            'diagnostics' => [
                'selected_count' => count($selected),
                'hidden_count' => count($hidden),
                'hidden' => $hidden,
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, viewAllHref: string}
     */
    private function buildSeasonalDrops(string $locale, HomeBuilderService $homeBuilder): array
    {
        $campaigns = StorefrontCampaign::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (StorefrontCampaign $campaign) => $campaign->isActiveForLocale($locale))
            ->filter(fn (StorefrontCampaign $campaign) => in_array($campaign->type, ['seasonal', 'drop', 'event'], true));

        $campaignItems = [];

        foreach ($campaigns as $campaign) {
            $campaignItems[] = [
                'id' => 'campaign-' . $campaign->id,
                'kind' => 'campaign',
                'entityId' => $campaign->id,
                'entitySlug' => $campaign->slug,
                'kicker' => $campaign->localizedValue('hero_kicker', $locale) ?? strtoupper($campaign->type),
                'title' => $campaign->localizedValue('name', $locale) ?? $campaign->name,
                'subtitle' => $campaign->localizedValue('hero_subtitle', $locale) ?? '',
                'image' => $homeBuilder->normalizeImage($campaign->hero_image),
                'href' => '/products?campaign=' . urlencode((string) $campaign->slug),
                'tag' => $campaign->stacking_mode === 'exclusive' ? 'Exclusive' : 'Drop',
            ];
        }

        $items = array_slice($campaignItems, 0, 6);

        return [
            'items' => $items,
            'viewAllHref' => $items[0]['href'] ?? '/products',
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, viewAllHref: string}
     */
    private function buildHomeCollections(string $locale, HomeBuilderService $homeBuilder): array
    {
        $allCollections = StorefrontCollection::query()
            ->orderBy('display_order')
            ->get()
            ->filter(fn (StorefrontCollection $collection) => in_array($collection->type, ['seasonal', 'drop', 'guide', 'collection'], true));

        $collections = $allCollections
            ->filter(fn (StorefrontCollection $collection) => $collection->isActiveForLocale($locale));

        if ($collections->isEmpty()) {
            $now = now();

            $collections = $allCollections
                ->filter(fn (StorefrontCollection $collection) => $collection->is_active && $collection->isVisibleForLocale($locale))
                ->sortBy(function (StorefrontCollection $collection) use ($locale, $now) {
                    $schedule = $collection->resolveScheduleForLocale($locale);
                    $timezone = $schedule['timezone'] ?: config('app.timezone');
                    $startsAt = $schedule['starts_at']
                        ? Carbon::parse($schedule['starts_at'], $timezone)->getTimestamp()
                        : PHP_INT_MAX;

                    return [
                        $startsAt < $now->copy()->timezone($timezone)->getTimestamp() ? 1 : 0,
                        $startsAt,
                        (int) ($collection->display_order ?? PHP_INT_MAX),
                    ];
                })
                ->values();
        }

        $items = [];

        foreach ($collections as $collection) {
            $items[] = [
                'id' => 'collection-' . $collection->id,
                'kind' => 'collection',
                'entityId' => $collection->id,
                'entitySlug' => $collection->slug,
                'kicker' => $collection->localizedValue('hero_kicker', $locale) ?? strtoupper($collection->type),
                'title' => $collection->localizedValue('title', $locale) ?? $collection->title,
                'subtitle' => $collection->localizedValue('description', $locale) ?? '',
                'image' => $homeBuilder->normalizeImage($collection->hero_image),
                'href' => '/collections/' . urlencode((string) $collection->slug),
                'tag' => $collection->type === 'guide' ? 'Guide' : 'Collection',
            ];
        }

        $items = array_slice($items, 0, 6);

        return [
            'items' => $items,
            'viewAllHref' => $items[0]['href'] ?? '/collections',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $homepagePromotions
     * @return array{items: array<int, array<string,mixed>>, viewAllHref: string}
     */
    private function buildFlashDeals(array $homepagePromotions): array
    {
        $flashPromotions = collect($homepagePromotions)
            ->filter(fn ($promo) => ($promo['type'] ?? null) === 'flash_sale')
            ->values();

        if ($flashPromotions->isEmpty()) {
            return [
                'items' => [],
                'viewAllHref' => '/promotions/flash-sales',
            ];
        }

        $targets = $flashPromotions->flatMap(fn ($promo) => $promo['targets'] ?? []);
        $productIds = $targets
            ->filter(fn ($target) => ($target['target_type'] ?? null) === 'product')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $categoryIds = $targets
            ->filter(fn ($target) => ($target['target_type'] ?? null) === 'category')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if (! empty($productIds) || ! empty($categoryIds)) {
            $query->where(function ($builder) use ($productIds, $categoryIds) {
                if (! empty($productIds)) {
                    $builder->whereIn('id', $productIds);
                }

                if (! empty($categoryIds)) {
                    $builder->orWhereIn('category_id', $categoryIds);
                }
            });
        }

        $products = $query
            ->latest()
            ->take(12)
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product))
            ->values()
            ->all();

        return [
            'items' => $products,
            'viewAllHref' => '/products?promotion_type=flash_sale',
        ];
    }

    private function resolveCategoryHighlights(?HomePageSetting $homeContent): Collection
    {
        $locale = app()->getLocale();
        $configured = $homeContent?->category_highlights ?? [];

        if (is_array($configured) && $configured !== []) {
            $categoryIds = collect($configured)
                ->map(fn ($entry) => (int) ($entry['category_id'] ?? 0))
                ->filter()
                ->unique()
                ->values();

            $categories = Category::query()
                ->withCount(['products as products_count' => fn ($q) => $q->where('is_active', true)])
                ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
                ->whereIn('id', $categoryIds)
                ->get()
                ->keyBy('id');

            return collect($configured)
                ->map(function ($entry) use ($categories, $locale) {
                    $categoryId = (int) ($entry['category_id'] ?? 0);
                    $category = $categories->get($categoryId);
                    if (! $category || ($category->products_count ?? 0) <= 0) {
                        return null;
                    }

                    return [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'name' => $category->translatedValue('name', $locale),
                        'count' => $category->products_count,
                        'views' => $category->view_count ?? 0,
                    ];
                })
                ->filter()
                ->values();
        }

        return Category::query()
            ->withCount(['products as products_count' => fn ($q) => $q->where('is_active', true)])
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('view_count')
            ->orderByDesc('products_count')
            ->take(8)
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->translatedValue('name', $locale),
                'count' => $category->products_count,
                'views' => $category->view_count ?? 0,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildFeaturedCategorySections(Collection $categoryHighlights, int $productsPerCategory = 8, int $maxCategories = 4): Collection
    {
        $locale = app()->getLocale();

        $categoryIds = $categoryHighlights
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take($maxCategories)
            ->values();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $categories = Category::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        $productsByCategory = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByRaw('MD5(CONCAT(products.id, ?))', [now()->toDateString()])
            ->get()
            ->groupBy('category_id');

        return $categoryIds
            ->map(function (int $categoryId) use ($categories, $productsByCategory, $productsPerCategory, $locale) {
                $category = $categories->get($categoryId);
                if (! $category) {
                    return null;
                }

                $products = ($productsByCategory->get($categoryId) ?? collect())
                    ->take($productsPerCategory)
                    ->map(fn (Product $product) => $this->transformProduct($product))
                    ->values();

                if ($products->isEmpty()) {
                    return null;
                }

                $categoryName = $category->translatedValue('name', $locale);
                $categorySlug = $category->slug;

                return [
                    'id' => $category->id,
                    'name' => $categoryName,
                    'slug' => $categorySlug,
                    'viewAllHref' => $categorySlug
                        ? '/categories/' . urlencode((string) $categorySlug)
                        : '/products?category=' . urlencode((string) $categoryName),
                    'products' => $products,
                ];
            })
            ->filter()
            ->values();
    }

    private function transformBanner(StorefrontBanner $banner): array
    {
        $locale = app()->getLocale();
        $targeting = is_array($banner->targeting ?? null) ? $banner->targeting : [];

        return [
            'id' => $banner->id,
            'title' => $banner->localizedValue('title', $locale),
            'description' => $banner->localizedValue('description', $locale),
            'type' => $banner->type,
            'displayType' => $banner->display_type,
            'imagePath' => $this->resolveBannerImage($banner),
            'backgroundColor' => $banner->background_color,
            'textColor' => $banner->text_color,
            'badgeText' => $banner->localizedValue('badge_text', $locale),
            'badgeColor' => $banner->badge_color,
            'ctaText' => $banner->localizedValue('cta_text', $locale),
            'ctaUrl' => $banner->getCtaUrl(),
            'imageMode' => $targeting['image_mode'] ?? 'split',
            'startsAt' => optional($banner->starts_at)->toIso8601String(),
            'endsAt' => optional($banner->ends_at)->toIso8601String(),
        ];
    }

    private function resolveBannerImage(StorefrontBanner $banner): ?string
    {
        $image = $banner->image_path;
        if ($image) {
            return app(HomeBuilderService::class)->normalizeImage($image);
        }

        if ($banner->target_type === 'product' && $banner->product) {
            return $this->resolveProductImage($banner->product);
        }

        if ($banner->target_type === 'category' && $banner->category) {
            return $this->resolveCategoryImage($banner->category);
        }

        return null;
    }

    private function resolveProductImage(Product $product): ?string
    {
        $image = $product->images?->first()?->url ?? null;

        return app(HomeBuilderService::class)->normalizeImage($image);
    }

    private function resolveCategoryImage(Category $category): ?string
    {
        return app(HomeBuilderService::class)->normalizeImage($category->hero_image ?? null);
    }

    /**
     * @param array<int, array<string, mixed>> $promotions
     * @return array<int, array<string, mixed>>
     */
    private function buildPromotionBanners(array $promotions): array
    {
        if (empty($promotions)) {
            return [];
        }

        $targets = collect($promotions)->flatMap(fn ($promo) => $promo['targets'] ?? []);
        $productIds = $targets->filter(fn ($t) => ($t['target_type'] ?? null) === 'product')
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $categoryIds = $targets->filter(fn ($t) => ($t['target_type'] ?? null) === 'category')
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->with('images')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        return collect($promotions)
            ->filter(fn ($promo) => ! empty($promo['targets']))
            ->map(function (array $promo) use ($products, $categories) {
                $targets = $promo['targets'] ?? [];
                $productTarget = collect($targets)->firstWhere('target_type', 'product');
                $categoryTarget = collect($targets)->firstWhere('target_type', 'category');

                $image = null;
                $ctaUrl = '/promotions';

                if ($productTarget && $products->has($productTarget['target_id'])) {
                    $product = $products->get($productTarget['target_id']);
                    $image = $this->resolveProductImage($product);
                    $ctaUrl = route('products.show', $product, false);
                } elseif ($categoryTarget && $categories->has($categoryTarget['target_id'])) {
                    $category = $categories->get($categoryTarget['target_id']);
                    $image = $this->resolveCategoryImage($category);
                    $ctaUrl = route('categories.show', $category, false);
                }

                return [
                    'id' => 'promo-' . $promo['id'],
                    'title' => $promo['name'] ?? 'Promotion',
                    'description' => $promo['description'] ?? null,
                    'type' => 'promotion',
                    'displayType' => 'carousel',
                    'imagePath' => $image,
                    'backgroundColor' => '#111827',
                    'textColor' => '#ffffff',
                    'badgeText' => $promo['badge_text'] ?? 'Promotion',
                    'badgeColor' => '#f59e0b',
                    'ctaText' => __('Shop now'),
                    'ctaUrl' => $ctaUrl,
                    'promotion' => $promo,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeStorefrontUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return null;
    }
}
