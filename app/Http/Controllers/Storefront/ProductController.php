<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\FormatsCategories;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use App\Services\ProductRecommendationService;
use App\Services\Promotions\PromotionHomepageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use TransformsProducts;
    use FormatsCategories;

    public function index(Request $request): Response
    {
        $locale = app()->getLocale();

        $category = $request->query('category');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $query = $request->query('q');
        $sort = $request->query('sort');
        $rating = $request->query('rating');
        $inStock = filter_var($request->query('in_stock'), FILTER_VALIDATE_BOOLEAN);
        $featured = $request->query('is_featured');

        $collectionFilter = $request->query('collection');
        $campaignFilter = $request->query('campaign');
        $promotionFilter = $request->query('promotion');
        $promotionTypeFilter = $request->query('promotion_type');

        $productQuery = Product::query()
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

        $filterContext = [];

        if ($category) {
            $matchedCategory = $this->findCategoryByIdentifier($category, $locale);
            if ($matchedCategory) {
                $categoryIds = $this->collectDescendantCategoryIds($matchedCategory);
                $productQuery->whereIn('category_id', $categoryIds);
                $filterContext['category'] = [
                    'id' => $matchedCategory->id,
                    'name' => $matchedCategory->translatedValue('name', $locale),
                    'slug' => $matchedCategory->slug,
                ];
            } else {
                // Fallback to maintain behavior if category not found
                $productQuery->whereHas('category', function ($builder) use ($category, $locale) {
                    $builder
                        ->where('name', $category)
                        ->orWhere('slug', $category)
                        ->orWhereHas('translations', function ($translations) use ($category, $locale) {
                            $translations
                                ->where('locale', $locale)
                                ->where('name', $category);
                        });
                });
            }
        }

        if ($minPrice !== null && is_numeric($minPrice)) {
            $productQuery->where('selling_price', '>=', (float) $minPrice);
        }

        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $productQuery->where('selling_price', '<=', (float) $maxPrice);
        }

        if ($query) {
            $productQuery->where(function ($builder) use ($query, $locale) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');

                $builder->orWhereHas('translations', function ($translations) use ($query, $locale) {
                    $translations
                        ->where('locale', $locale)
                        ->where(function ($translated) use ($query) {
                            $translated
                                ->where('name', 'like', '%' . $query . '%')
                                ->orWhere('description', 'like', '%' . $query . '%');
                        });
                });

                $builder->orWhereHas('category', function ($categoryBuilder) use ($query, $locale) {
                    $categoryBuilder
                        ->where('name', 'like', '%' . $query . '%')
                        ->orWhereHas('translations', function ($translations) use ($query, $locale) {
                            $translations
                                ->where('locale', $locale)
                                ->where('name', 'like', '%' . $query . '%');
                        });
                });
            });
        }

        if ($rating !== null && is_numeric($rating)) {
            $productQuery->having('reviews_avg_rating', '>=', (float) $rating);
        }

        if ($inStock) {
            $productQuery->where('stock_on_hand', '>', 0);
        }

        if ($featured !== null && $featured !== '') {
            if ($featured === '1' || $featured === 1 || $featured === true || $featured === 'true') {
                $productQuery->where('is_featured', true);
            } elseif ($featured === '0' || $featured === 0 || $featured === false || $featured === 'false') {
                $productQuery->where('is_featured', false);
            }
        }

        $this->applyCollectionFilter($productQuery, $collectionFilter, $locale, $filterContext);
        $this->applyCampaignFilter($productQuery, $campaignFilter, $locale, $filterContext);
        $this->applyPromotionFilters($productQuery, $promotionFilter, $promotionTypeFilter, $filterContext);

        $sortable = [
            'price_asc' => ['selling_price', 'asc'],
            'price_desc' => ['selling_price', 'desc'],
            'newest' => ['created_at', 'desc'],
            'rating' => ['reviews_avg_rating', 'desc'],
            'popularity' => ['reviews_count', 'desc'],
            'featured' => ['is_featured', 'desc'],
        ];

        if ($sort && isset($sortable[$sort])) {
            [$field, $direction] = $sortable[$sort];
            $productQuery->orderBy($field, $direction);

            if ($sort === 'featured') {
                $productQuery->orderBy('created_at', 'desc');
            }
        } else {
            $productQuery->orderBy('created_at', 'desc');
        }

        $perPage = 18;
        $products = $productQuery
            ->paginate($perPage)
            ->through(fn (Product $product) => $this->transformProduct($product));

        $categories = $this->rootCategoriesTree(['children', 'children.children']);

        $productIds = $products->getCollection()->pluck('id')->all();
        $categoryIds = $products->getCollection()->pluck('category_id')->filter()->unique()->values()->all();
        $promotions = app(PromotionHomepageService::class)->getPromotionsForPlacement('product', $productIds, $categoryIds);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'currency' => 'USD',
            'categories' => $categories,
            'promotions' => $promotions,
            'filterContext' => $filterContext,
            'filters' => [
                'category' => $category,
                'collection' => $collectionFilter,
                'campaign' => $campaignFilter,
                'promotion' => $promotionFilter,
                'promotion_type' => $promotionTypeFilter,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'q' => $query,
                'sort' => $sort,
                'rating' => $rating,
                'in_stock' => $inStock,
                'is_featured' => $featured,
                'page' => $products->currentPage(),
            ],
        ]);
    }

    /**
     * Resolve a category by slug, name, or translated name.
     */
    private function findCategoryByIdentifier(string $identifier, string $locale): ?Category
    {
        return Category::query()
            ->where('slug', $identifier)
            ->orWhere('name', $identifier)
            ->orWhereHas('translations', function ($translations) use ($identifier, $locale) {
                $translations
                    ->where('locale', $locale)
                    ->where('name', $identifier);
            })
            ->first();
    }

    /**
     * Collect the selected category id plus all descendant ids.
     *
     * @return \Illuminate\Support\Collection<int,int>
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

    public function show(Product $product): Response
    {
        abort_if(! $product->is_active, 404);
        $locale = app()->getLocale();

        $product->load([
            'images',
            'variants',
            'category',
            'category.translations' => fn ($query) => $query->where('locale', $locale),
            'translations' => fn ($query) => $query->where('locale', $locale),
        ]);

        $reviews = ProductReview::query()
            ->with('customer')
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        $reviewHighlights = $reviews
            ->take(3)
            ->map(fn (ProductReview $review) => [
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'author' => $review->customer?->name ?? 'Verified buyer',
            ]);

        $reviewCount = $reviews->count();
        $reviewAverage = $reviewCount ? round($reviews->avg('rating'), 1) : 0.0;
        $reviewBreakdown = collect([5, 4, 3, 2, 1])->mapWithKeys(function ($rating) use ($reviews) {
            return [$rating => $reviews->where('rating', $rating)->count()];
        })->all();

        $recommendationService = app(ProductRecommendationService::class);

        $related = $recommendationService
            ->relatedProducts($product, 4)
            ->map(fn (Product $relatedProduct) => $this->transformProduct($relatedProduct));

        $customer = Auth::guard('customer')->user();
        $reviewableItems = [];

        if ($customer) {
            $reviewableItems = OrderItem::query()
                ->with('order')
                ->where('fulfillment_status', 'fulfilled')
                ->whereHas('shipments', function ($builder) {
                    $builder->whereNotNull('delivered_at');
                })
                ->whereHas('order', function ($builder) use ($customer) {
                    $builder
                        ->where('customer_id', $customer->id)
                        ->where('status', 'fulfilled');
                })
                ->whereHas('productVariant', function ($builder) use ($product) {
                    $builder->where('product_id', $product->id);
                })
                ->whereDoesntHave('review')
                ->latest()
                ->get()
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'order_number' => $item->order?->number,
                    'ordered_at' => $item->order?->placed_at,
                ])
                ->values()
                ->all();
        }

        $personalized = collect();
        if ($customer) {
            $personalized = $recommendationService
                ->personalized($customer, 6)
                ->map(fn (Product $p) => $this->transformProduct($p));
        }

        $promotions = app(PromotionHomepageService::class)->getPromotionsForPlacement(
            'product',
            [$product->id],
            [$product->category_id]
        );

        return Inertia::render('Products/Show', [
            'product' => $this->transformProduct($product, true),
            'currency' => $product->currency ?? 'USD',
            'promotions' => $promotions,
            'errorMessages'=>session('error'),
            'reviews' => $reviews->map(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'images' => $review->images ?? [],
                'verified_purchase' => (bool) $review->verified_purchase,
                'helpful_count' => $review->helpful_count ?? 0,
                'created_at' => $review->created_at,
                'author' => $review->customer?->name ?? 'Verified buyer',
                'errorMessages'=>session('errors')

            ]),
            'reviewSummary' => [
                'count' => $reviewCount,
                'average' => $reviewAverage,
                'breakdown' => $reviewBreakdown,
            ],
            'breadcrumbs' => $this->buildProductBreadcrumbs($product, $locale),
            'reviewHighlights' => $reviewHighlights,
            'relatedProducts' => $related,
            'personalizedProducts' => $personalized,
            'reviewableItems' => $reviewableItems,
        ]);
    }

    /**
     * @return array<int, array{label:string, href:?string}>
     */
    private function buildProductBreadcrumbs(Product $product, string $locale): array
    {
        $items = [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'Products', 'href' => '/products'],
        ];

        if ($product->category) {
            $items[] = [
                'label' => $product->category->translatedValue('name', $locale),
                'href' => '/categories/' . urlencode($product->category->slug ?? (string) $product->category->id),
            ];
        }

        $translatedName = $product->translations
            ->firstWhere('locale', $locale)?->name;

        $items[] = [
            'label' => $translatedName ?: $product->name,
            'href' => null,
        ];

        return $items;
    }

    private function applyCollectionFilter(Builder $productQuery, mixed $collectionFilter, string $locale, array &$filterContext): void
    {
        if (! is_string($collectionFilter) && ! is_numeric($collectionFilter)) {
            return;
        }

        $collection = StorefrontCollection::query()
            ->where(is_numeric($collectionFilter) ? 'id' : 'slug', $collectionFilter)
            ->first();

        if (! $collection || ! $collection->isActiveForLocale($locale)) {
            $productQuery->whereRaw('1 = 0');
            $filterContext['collection'] = [
                'value' => (string) $collectionFilter,
                'status' => 'not_found',
            ];

            return;
        }

        $productIds = $collection->resolveProducts($locale)->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        if (empty($productIds)) {
            $productQuery->whereRaw('1 = 0');
        } else {
            $productQuery->whereIn('products.id', $productIds);
        }

        $filterContext['collection'] = [
            'id' => $collection->id,
            'slug' => $collection->slug,
            'title' => $collection->localizedValue('title', $locale) ?? $collection->title,
            'status' => 'ok',
        ];
    }

    private function applyCampaignFilter(Builder $productQuery, mixed $campaignFilter, string $locale, array &$filterContext): void
    {
        if (! is_string($campaignFilter) && ! is_numeric($campaignFilter)) {
            return;
        }

        $campaign = StorefrontCampaign::query()
            ->where(is_numeric($campaignFilter) ? 'id' : 'slug', $campaignFilter)
            ->first();

        if (! $campaign || ! $campaign->isActiveForLocale($locale)) {
            $productQuery->whereRaw('1 = 0');
            $filterContext['campaign'] = [
                'value' => (string) $campaignFilter,
                'status' => 'not_found',
            ];

            return;
        }

        $campaignCollectionIds = $campaign->collectionIds();
        $collectionProductIds = StorefrontCollection::query()
            ->whereIn('id', $campaignCollectionIds)
            ->get()
            ->flatMap(fn (StorefrontCollection $collection) => $collection->resolveProducts($locale)->pluck('id')->all())
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $promotionTargets = Promotion::query()
            ->whereIn('id', $campaign->promotionIds())
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->with('targets')
            ->get()
            ->flatMap(fn (Promotion $promotion) => $promotion->targets);

        $promotionProductIds = $promotionTargets
            ->where('target_type', 'product')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $promotionCategoryIds = $promotionTargets
            ->where('target_type', 'category')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $categoryProductIds = empty($promotionCategoryIds)
            ? []
            : Product::query()
                ->where('is_active', true)
                ->whereIn('category_id', $promotionCategoryIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

        $productIds = collect($collectionProductIds)
            ->concat($promotionProductIds)
            ->concat($categoryProductIds)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            $productQuery->whereRaw('1 = 0');
        } else {
            $productQuery->whereIn('products.id', $productIds);
        }

        $filterContext['campaign'] = [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'title' => $campaign->localizedValue('name', $locale) ?? $campaign->name,
            'status' => 'ok',
        ];
    }

    private function applyPromotionFilters(Builder $productQuery, mixed $promotionFilter, mixed $promotionTypeFilter, array &$filterContext): void
    {
        if (! is_numeric($promotionFilter) && ! is_string($promotionTypeFilter)) {
            return;
        }

        $promotionQuery = Promotion::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->with('targets');

        if (is_numeric($promotionFilter)) {
            $promotionQuery->where('id', (int) $promotionFilter);
        }

        if (is_string($promotionTypeFilter) && $promotionTypeFilter !== '') {
            $promotionQuery->where('type', $promotionTypeFilter);
        }

        $promotions = $promotionQuery->get();

        if ($promotions->isEmpty()) {
            $productQuery->whereRaw('1 = 0');
            $filterContext['promotion'] = [
                'status' => 'not_found',
                'promotion' => is_numeric($promotionFilter) ? (int) $promotionFilter : null,
                'promotion_type' => is_string($promotionTypeFilter) ? $promotionTypeFilter : null,
            ];

            return;
        }

        $targets = $promotions->flatMap(fn (Promotion $promotion) => $promotion->targets);
        $productIds = $targets
            ->where('target_type', 'product')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $categoryIds = $targets
            ->where('target_type', 'category')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($productIds) || ! empty($categoryIds)) {
            $productQuery->where(function ($builder) use ($productIds, $categoryIds) {
                if (! empty($productIds)) {
                    $builder->whereIn('products.id', $productIds);
                }

                if (! empty($categoryIds)) {
                    $builder->orWhereIn('category_id', $categoryIds);
                }
            });
        }

        $filterContext['promotion'] = [
            'status' => 'ok',
            'count' => $promotions->count(),
            'promotion' => is_numeric($promotionFilter) ? (int) $promotionFilter : null,
            'promotion_type' => is_string($promotionTypeFilter) ? $promotionTypeFilter : null,
            'sitewide' => empty($productIds) && empty($categoryIds),
        ];
    }
}
