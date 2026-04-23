<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use App\Services\Storefront\ProductMetaExtractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CollectionController extends ApiController
{
    public function __construct(
        private readonly ProductMetaExtractor $productMetaExtractor,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        $collections = Cache::remember("mobile:collections:index:{$locale}", now()->addMinutes(5), function () use ($locale) {
            return StorefrontCollection::query()
                ->orderBy('display_order')
                ->get()
                ->filter(fn (StorefrontCollection $c) => $c->isActiveForLocale($locale))
                ->values()
                ->map(fn (StorefrontCollection $c) => $this->transformSummary($c, $locale))
                ->values()
                ->all();
        });

        return $this->success($collections);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $collection = StorefrontCollection::query()->where('slug', $slug)->first();
        $locale = app()->getLocale();
        $requestFilters = $this->requestFilters($request);

        if (! $collection) {
            $fallbackCategorySlug = $this->legacyCollectionCategorySlug($slug);

            if ($fallbackCategorySlug) {
                return $this->legacyCategoryCollectionResponse(
                    $request,
                    $fallbackCategorySlug,
                    $slug,
                    $locale,
                    $requestFilters
                );
            }

            return $this->notFound('Collection not found');
        }

        abort_if(! $collection->isActiveForLocale($locale), 404);

        $perPage = min((int) ($request->query('per_page', 18)), 50);
        $page = max((int) ($request->query('page', 1)), 1);

        try {
            $resolvedProducts = $collection->paginateFilteredProducts($requestFilters, $locale, $perPage, $page);
            $items = collect($resolvedProducts->items())->values();
            $filters = $collection->availableFilters($locale);
            $total = $resolvedProducts->total();
            $lastPage = $resolvedProducts->lastPage();
        } catch (\Throwable $exception) {
            Log::error('Mobile collection render failed', [
                'collection_id' => $collection->id,
                'collection_slug' => $collection->slug,
                'locale' => $locale,
                'filters' => $requestFilters,
                'error' => $exception->getMessage(),
            ]);

            $items = collect();
            $filters = $this->buildFilters($items);
            $total = 0;
            $lastPage = 1;
        }

        return $this->success(
            [
                'collection' => $this->transformDetail($collection, $locale),
                'products' => ProductResource::collection($items)->resolve(),
                'filters' => $filters,
            ],
            null,
            200,
            [
                'currentPage' => $page,
                'lastPage' => $lastPage,
                'perPage' => $perPage,
                'total' => $total,
            ]
        );
    }

    private function transformSummary(StorefrontCollection $collection, string $locale): array
    {
        return [
            'id' => $collection->id,
            'slug' => $collection->slug,
            'type' => $collection->type,
            'title' => $collection->localizedValue('title', $locale),
            'description' => $collection->localizedValue('description', $locale),
            'hero_kicker' => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle' => $collection->localizedValue('hero_subtitle', $locale),
            'hero_image' => $this->resolveImage($collection->hero_image),
            'starts_at' => $collection->starts_at?->toIso8601String(),
            'ends_at' => $collection->ends_at?->toIso8601String(),
        ];
    }

    private function transformDetail(StorefrontCollection $collection, string $locale): array
    {
        return [
            'id' => $collection->id,
            'slug' => $collection->slug,
            'type' => $collection->type,
            'title' => $collection->localizedValue('title', $locale),
            'description' => $collection->localizedValue('description', $locale),
            'hero_kicker' => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle' => $collection->localizedValue('hero_subtitle', $locale),
            'hero_image' => $this->resolveImage($collection->hero_image),
            'hero_cta_label' => $collection->localizedValue('hero_cta_label', $locale),
            'hero_cta_url' => $collection->localizedValue('hero_cta_url', $locale),
            'content' => $collection->localizedValue('content', $locale),
            'seo_title' => $collection->localizedValue('seo_title', $locale),
            'seo_description' => $collection->localizedValue('seo_description', $locale),
            'starts_at' => $collection->starts_at?->toIso8601String(),
            'ends_at' => $collection->ends_at?->toIso8601String(),
        ];
    }

    private function resolveImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(\Storage::url($path));
    }

    private function buildFilters(Collection $products): array
    {
        $priceValues = $products
            ->map(function ($product): ?float {
                $sellingPrice = is_array($product)
                    ? ($product['selling_price'] ?? $product['price'] ?? null)
                    : ($product->selling_price ?? null);

                if ($sellingPrice !== null && is_numeric($sellingPrice)) {
                    return (float) $sellingPrice;
                }

                return null;
            })
            ->filter(fn ($value): bool => $value !== null)
            ->values();

        return [
            'price_range' => [
                'min' => $priceValues->isNotEmpty() ? round((float) $priceValues->min(), 2) : null,
                'max' => $priceValues->isNotEmpty() ? round((float) $priceValues->max(), 2) : null,
            ],
            ...$this->productMetaExtractor->extract($products->all()),
        ];
    }

    private function requestFilters(Request $request): array
    {
        $attributes = $request->query('attributes', []);

        if (! is_array($attributes)) {
            $attributes = [];
        }

        return [
            'sort' => $request->query('sort'),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'in_stock' => $request->boolean('in_stock'),
            'brand' => $request->query('brand'),
            'attributes' => $attributes,
        ];
    }

    private function filtersFromQuery(Builder $query): array
    {
        $metaQuery = (clone $query)->setEagerLoads([]);

        $aggregate = (clone $metaQuery)
            ->reorder()
            ->selectRaw('MIN(selling_price) as min_price, MAX(selling_price) as max_price')
            ->first();

        return [
            'price_range' => [
                'min' => is_numeric($aggregate?->min_price) ? round((float) $aggregate->min_price, 2) : null,
                'max' => is_numeric($aggregate?->max_price) ? round((float) $aggregate->max_price, 2) : null,
            ],
            ...$this->productMetaExtractor->extractFromQuery($metaQuery),
        ];
    }

    private function applyRequestFiltersToQuery(Builder $query, array $filters): Builder
    {
        $minPrice = Arr::get($filters, 'min_price');
        if ($minPrice !== null && is_numeric($minPrice)) {
            $query->where('selling_price', '>=', (float) $minPrice);
        }

        $maxPrice = Arr::get($filters, 'max_price');
        if ($maxPrice !== null && is_numeric($maxPrice)) {
            $query->where('selling_price', '<=', (float) $maxPrice);
        }

        if (Arr::get($filters, 'in_stock') === true) {
            $query->where('stock_on_hand', '>', 0);
        }

        $brand = trim((string) Arr::get($filters, 'brand', ''));
        if ($brand !== '') {
            $query->where(function (Builder $builder) use ($brand) {
                $builder
                    ->where('attributes->brand', $brand)
                    ->orWhereJsonContains('attributes->brand', $brand);
            });
        }

        $attributes = Arr::get($filters, 'attributes', []);
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $normalizedValue = trim((string) (is_array($value) ? reset($value) : $value));
                if ($normalizedValue === '') {
                    continue;
                }

                $query->where(function (Builder $builder) use ($key, $normalizedValue) {
                    $builder
                        ->where('attributes->' . $key, $normalizedValue)
                        ->orWhereJsonContains('attributes->' . $key, $normalizedValue);
                });
            }
        }

        return $query;
    }

    private function applyRequestSort(Builder $query, ?string $sort): void
    {
        $sortable = [
            'price_asc' => ['selling_price', 'asc'],
            'price_desc' => ['selling_price', 'desc'],
            'newest' => ['created_at', 'desc'],
            'rating' => ['reviews_avg_rating', 'desc'],
            'popularity' => ['reviews_count', 'desc'],
            'popular' => ['reviews_count', 'desc'],
            'featured' => ['is_featured', 'desc'],
        ];

        $query->reorder();

        if ($sort && isset($sortable[$sort])) {
            [$field, $direction] = $sortable[$sort];
            $query->orderBy($field, $direction);
            if ($sort === 'featured') {
                $query->orderBy('created_at', 'desc');
            }
            return;
        }

        $query->latest();
    }

    private function legacyCollectionCategorySlug(string $slug): ?string
    {
        return [
            'women' => 'womens-clothing',
            'womens' => 'womens-clothing',
            'women-collection' => 'womens-clothing',
            'womens-collection' => 'womens-clothing',
            'men' => 'mens-clothing',
            'mens' => 'mens-clothing',
            'men-collection' => 'mens-clothing',
            'mens-collection' => 'mens-clothing',
        ][strtolower(trim($slug))] ?? null;
    }

    private function legacyCategoryCollectionResponse(
        Request $request,
        string $categorySlug,
        string $requestedSlug,
        string $locale,
        array $requestFilters
    ): JsonResponse {
        $category = Category::query()
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->first();

        if (! $category) {
            return $this->notFound('Collection not found');
        }

        $perPage = min((int) ($request->query('per_page', 18)), 50);
        $page = max((int) ($request->query('page', 1)), 1);
        $categoryIds = $this->descendantCategoryIds([(int) $category->id]);

        $baseQuery = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds);

        $filters = $this->filtersFromQuery($baseQuery);

        $query = (clone $baseQuery)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $query = $this->applyRequestFiltersToQuery($query, $requestFilters);
        $this->applyRequestSort($query, Arr::get($requestFilters, 'sort'));

        $products = $query->paginate($perPage, ['*'], 'page', $page);
        $items = collect($products->items())->values();

        return $this->success(
            [
                'collection' => [
                    'id' => 'legacy-category-' . $category->id,
                    'slug' => $requestedSlug,
                    'type' => 'legacy-category',
                    'title' => method_exists($category, 'translatedValue')
                        ? $category->translatedValue('name', $locale)
                        : $category->name,
                    'description' => null,
                    'hero_kicker' => 'Collection',
                    'hero_subtitle' => null,
                    'hero_image' => null,
                    'hero_cta_label' => null,
                    'hero_cta_url' => null,
                    'content' => null,
                    'seo_title' => null,
                    'seo_description' => null,
                    'starts_at' => null,
                    'ends_at' => null,
                ],
                'products' => ProductResource::collection($items)->resolve(),
                'filters' => $filters,
            ],
            null,
            200,
            [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ]
        );
    }

    /**
     * @param array<int, int> $rootIds
     * @return array<int, int>
     */
    private function descendantCategoryIds(array $rootIds): array
    {
        $rootIds = collect($rootIds)->map(fn ($id) => (int) $id)->filter()->values()->all();

        if ($rootIds === []) {
            return [];
        }

        $all = $rootIds;
        $frontier = $rootIds;

        for ($i = 0; $i < 12; $i++) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $children = array_values(array_diff($children, $all));

            if ($children === []) {
                break;
            }

            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return array_values(array_unique($all));
    }
}
