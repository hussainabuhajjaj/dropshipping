<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;
use App\Services\Promotions\PromotionHomepageService;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\HomePageSetting;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontBanner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    public function index(PromotionHomepageService $promotionHomepageService): Response
    {
        // NOTE: Home page data assembly is duplicated in Api\\Storefront\\HomeController.
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

        $categoryList = $this->rootCategoriesTree(['children', 'children.children']);

        $homeContent = HomePageSetting::query()->latest()->first();
        $categoryHighlights = $this->resolveCategoryHighlights($homeContent);
        $heroSlides = $homeContent?->hero_slides ?? [];
        if (is_array($heroSlides)) {
            $heroSlides = collect($heroSlides)->map(function (array $slide) {
                $image = $slide['image'] ?? null;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $slide['image'] = Storage::url($image);
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
            return $this->resolveImagePath($image);
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
        return $this->resolveImagePath($image);
    }

    private function resolveCategoryImage(Category $category): ?string
    {
        return $this->resolveImagePath($category->hero_image ?? null);
    }

    private function resolveImagePath(?string $path): ?string
    {
        // NOTE: Image URL normalization is duplicated in HandleInertiaRequests
        // and the API HomeController. Keep in sync if URL handling changes.
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
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
