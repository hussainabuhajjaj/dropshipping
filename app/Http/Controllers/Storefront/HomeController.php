<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;
use App\Services\Promotions\PromotionHomepageService;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\Product;
use App\Models\StorefrontBanner;
use App\Services\Storefront\HomeBuilderService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    public function index(PromotionHomepageService $promotionHomepageService, HomeBuilderService $homeBuilder): Response
    {
        $sections = $homeBuilder->buildProductSections(6);
        $featured = $sections['featured'];
        $bestSellers = $sections['bestSellers'];
        $recommended = $sections['recommended'];

        $categoryList = $this->rootCategoriesTree(['children', 'children.children']);

        $homeContent = HomePageSetting::query()->latest()->first();
        $categoryHighlights = $this->resolveCategoryHighlights($homeContent);
        $heroSlides = $homeContent?->hero_slides ?? [];
        if (is_array($heroSlides)) {
            $heroSlides = collect($heroSlides)->map(function (array $slide) {
                $image = $slide['image'] ?? null;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $slide['image'] = $homeBuilder->normalizeImage($image);
                }
                return $slide;
            })->values()->all();
        }

        $homepagePromotions = $promotionHomepageService->getHomepagePromotions();
        $promotionBanners = $this->buildPromotionBanners($homepagePromotions);

        // Fetch active banners
        $stripBanner = StorefrontBanner::active()
            ->byDisplayType('strip')
            ->where(function ($query) {
                $query->where('target_type', 'none')
                    ->orWhereNull('target_type');
            })
            ->with(['product.images', 'category'])
            ->orderBy('display_order')
            ->first();

        $heroBanners = StorefrontBanner::active()
                ->byDisplayType('hero')
                ->where(function ($query) {
                    $query->where('target_type', 'none')
                        ->orWhereNull('target_type');
                })
                ->with(['product.images', 'category'])
                ->orderBy('display_order')
                ->get()
                ->map(fn (StorefrontBanner $banner) => $this->transformBanner($banner))
                ->values()
                ->toArray();

        $carouselBanners = StorefrontBanner::active()
                ->byDisplayType('carousel')
                ->where(function ($query) {
                    $query->where('target_type', 'none')
                        ->orWhereNull('target_type');
                })
                ->with(['product.images', 'category'])
                ->orderBy('display_order')
                ->get()
                ->map(fn (StorefrontBanner $banner) => $this->transformBanner($banner))
                ->values()
                ->toArray();

        if (empty($heroBanners) && ! empty($promotionBanners)) {
            $heroBanners = [array_shift($promotionBanners)];
        }

        if (! empty($promotionBanners)) {
            $carouselBanners = array_values(array_merge($carouselBanners, $promotionBanners));
        }

        $banners = [
            'hero' => $heroBanners,
            'carousel' => $carouselBanners,
            'strip' => $stripBanner ? $this->transformBanner($stripBanner) : null,
        ];

        return Inertia::render('Home', [
            'featured' => $featured->map(fn (Product $product) => $this->transformProduct($product)),
            'bestSellers' => $bestSellers->map(fn (Product $product) => $this->transformProduct($product)),
            'recommended' => $recommended->map(fn (Product $product) => $this->transformProduct($product)),
            'categories' => $categoryList,
            'categoryHighlights' => $categoryHighlights,
            'currency' => 'USD',
            'banners' => $banners,
            'homeContent' => $homeContent ? [
                'top_strip' => $homeContent->top_strip,
                'hero_slides' => $heroSlides,
                'rail_cards' => $homeContent->rail_cards,
                'banner_strip' => $homeContent->banner_strip,
            ] : null,
            'homepagePromotions' => $homepagePromotions,
        ]);
    }

    private function resolveCategoryHighlights(?HomePageSetting $homeContent)
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

    private function transformBanner(StorefrontBanner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'description' => $banner->description,
            'type' => $banner->type,
            'displayType' => $banner->display_type,
            'imagePath' => $this->resolveBannerImage($banner),
            'backgroundColor' => $banner->background_color,
            'textColor' => $banner->text_color,
            'badgeText' => $banner->badge_text,
            'badgeColor' => $banner->badge_color,
            'ctaText' => $banner->cta_text,
            'ctaUrl' => $banner->getCtaUrl(),
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
                    $ctaUrl = route('products.show', $product);
                } elseif ($categoryTarget && $categories->has($categoryTarget['target_id'])) {
                    $category = $categories->get($categoryTarget['target_id']);
                    $image = $this->resolveCategoryImage($category);
                    $ctaUrl = route('categories.show', $category);
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
                    'ctaText' => 'Shop now',
                    'ctaUrl' => $ctaUrl,
                    'promotion' => $promo,
                ];
            })
            ->values()
            ->all();
    }

}
