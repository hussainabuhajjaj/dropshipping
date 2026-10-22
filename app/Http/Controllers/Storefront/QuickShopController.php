<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Services\Promotions\PromotionHomepageService;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class QuickShopController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    public function show(Request $request, string $lane, HomeBuilderService $homeBuilder): Response
    {
        $lanes = $this->laneDefinitions();
        abort_unless(isset($lanes[$lane]), 404);

        $locale = app()->getLocale();
        $productQuery = $this->baseProductQuery($locale);
        $this->applyLaneQuery($productQuery, $lane, $homeBuilder);

        return $this->renderLanePage($request, $productQuery, $lanes[$lane], $lanes);
    }

    public function category(Request $request, Category $category): Response
    {
        $locale = app()->getLocale();
        $categoryIds = $this->collectDescendantCategoryIds($category);

        $productQuery = $this->baseProductQuery($locale)
            ->whereIn('category_id', $categoryIds)
            ->orderByDesc('is_featured')
            ->latest('created_at');

        $title = method_exists($category, 'translatedValue')
            ? $category->translatedValue('name', $locale)
            : $category->name;

        $lane = [
            'key' => 'category-' . ($category->slug ?? $category->id),
            'label' => $title,
            'title' => $title,
            'eyebrow' => 'Collection',
            'subtitle' => 'A dedicated shopping page for this collection, with fresh picks and useful finds first.',
            'href' => '/quick-shop/category/' . urlencode((string) $category->slug),
            'tone' => 'category',
        ];

        return $this->renderLanePage($request, $productQuery, $lane, $this->laneDefinitions());
    }

    private function baseProductQuery(string $locale): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->with([
                'images',
                'category',
                'category.translations' => fn ($query) => $query->where('locale', $locale),
                'variants',
                'translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');
    }

    private function applyLaneQuery(Builder $query, string $lane, HomeBuilderService $homeBuilder): void
    {
        match ($lane) {
            'new-in' => $query->latest('created_at'),
            'sale' => $query
                ->whereHas('variants', function (Builder $variants) {
                    $variants
                        ->whereNotNull('compare_at_price')
                        ->whereColumn('compare_at_price', '>', 'price');
                })
                ->latest('created_at'),
            'best-sellers' => $this->applyBestSellerSort($query, $homeBuilder),
            default => $query
                ->orderByDesc('is_featured')
                ->orderByDesc('reviews_count')
                ->orderByDesc('reviews_avg_rating')
                ->latest('created_at'),
        };
    }

    private function applyBestSellerSort(Builder $query, HomeBuilderService $homeBuilder): void
    {
        $productIds = $homeBuilder->topSellingProductIds(240);

        if (empty($productIds)) {
            $query
                ->orderByDesc('reviews_count')
                ->orderByDesc('reviews_avg_rating')
                ->latest('created_at');

            return;
        }

        $query
            ->whereIn('products.id', $productIds)
            ->orderByRaw('FIELD(products.id, ' . implode(',', array_map('intval', $productIds)) . ')');
    }

    /**
     * @param array<string, mixed> $lane
     * @param array<string, array<string, string>> $lanes
     */
    private function renderLanePage(Request $request, Builder $query, array $lane, array $lanes): Response
    {
        $products = $query
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Product $product) => $this->transformProduct($product));

        $productIds = $products->getCollection()->pluck('id')->all();
        $categoryIds = $products->getCollection()->pluck('category_id')->filter()->unique()->values()->all();
        $promotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('product', $productIds, $categoryIds);

        return Inertia::render('QuickShop/Show', [
            'lane' => $lane,
            'lanes' => array_values($lanes),
            'products' => $products,
            'currency' => 'USD',
            'promotions' => $promotions,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Quick shop', 'href' => null],
                ['label' => $lane['label'], 'href' => null],
            ],
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function laneDefinitions(): array
    {
        return [
            'just-for-you' => [
                'key' => 'just-for-you',
                'label' => 'Just for You',
                'title' => 'Just for You',
                'eyebrow' => 'Quick shop',
                'subtitle' => 'A focused feed of featured, highly rated, and recently added products.',
                'href' => '/quick-shop/just-for-you',
                'tone' => 'default',
            ],
            'new-in' => [
                'key' => 'new-in',
                'label' => 'New In',
                'title' => 'New In',
                'eyebrow' => 'Fresh drops',
                'subtitle' => 'The newest products added to Simbazu, sorted for fast discovery.',
                'href' => '/quick-shop/new-in',
                'tone' => 'default',
            ],
            'sale' => [
                'key' => 'sale',
                'label' => 'Sale',
                'title' => 'Sale',
                'eyebrow' => 'Limited offers',
                'subtitle' => 'Products with active markdowns and compare-at savings.',
                'href' => '/quick-shop/sale',
                'tone' => 'sale',
            ],
            'best-sellers' => [
                'key' => 'best-sellers',
                'label' => 'Best Sellers',
                'title' => 'Best Sellers',
                'eyebrow' => 'Customer favorites',
                'subtitle' => 'Products ordered and reviewed most often by customers.',
                'href' => '/quick-shop/best-sellers',
                'tone' => 'default',
            ],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function collectDescendantCategoryIds(Category $root): Collection
    {
        $ids = collect([$root->id]);
        $queue = collect([$root->id]);

        while ($queue->isNotEmpty()) {
            $children = Category::query()
                ->whereIn('parent_id', $queue->all())
                ->pluck('id');

            $queue = $children;
            $ids = $ids->merge($children);
        }

        return $ids->unique()->values();
    }
}
