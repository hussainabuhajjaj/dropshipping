<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use App\Services\Storefront\ProductMetaExtractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        if (! $collection) {
            $fallbackCategorySlug = $this->legacyCollectionCategorySlug($slug);

            if ($fallbackCategorySlug) {
                return $this->legacyCategoryCollectionResponse($request, $fallbackCategorySlug, $slug, $locale);
            }

            return $this->notFound('Collection not found');
        }

        abort_if(! $collection->isActiveForLocale($locale), 404);

        $perPage = min((int) ($request->query('per_page', 18)), 50);
        $page    = max((int) ($request->query('page', 1)), 1);

        try {
            $resolvedProducts = $collection->paginateResolvedProducts($locale, $perPage, $page);
            $items = collect($resolvedProducts->items())->values();
            $filters = $this->buildFilters($items);
            $total = $resolvedProducts->total();
            $lastPage = $resolvedProducts->lastPage();
        } catch (\Throwable $exception) {
            Log::error('Mobile collection render failed', [
                'collection_id' => $collection->id,
                'collection_slug' => $collection->slug,
                'locale' => $locale,
                'error' => $exception->getMessage(),
            ]);

            $items = collect();
            $filters = $this->buildFilters($items);
            $total = 0;
            $lastPage = 1;
        }

        $productPayload = ProductResource::collection($items)
            ->resolve();

        return $this->success(
            [
                'collection' => $this->transformDetail($collection, $locale),
                'products'   => $productPayload,
                'filters'    => $filters,
            ],
            null,
            200,
            [
                'currentPage' => $page,
                'lastPage'    => $lastPage,
                'perPage'     => $perPage,
                'total'       => $total,
            ]
        );
    }

    private function transformSummary(StorefrontCollection $collection, string $locale): array
    {
        return [
            'id'           => $collection->id,
            'slug'         => $collection->slug,
            'type'         => $collection->type,
            'title'        => $collection->localizedValue('title', $locale),
            'description'  => $collection->localizedValue('description', $locale),
            'hero_kicker'  => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle'=> $collection->localizedValue('hero_subtitle', $locale),
            'hero_image'   => $this->resolveImage($collection->hero_image),
            'starts_at'    => $collection->starts_at?->toIso8601String(),
            'ends_at'      => $collection->ends_at?->toIso8601String(),
        ];
    }

    private function transformDetail(StorefrontCollection $collection, string $locale): array
    {
        return [
            'id'              => $collection->id,
            'slug'            => $collection->slug,
            'type'            => $collection->type,
            'title'           => $collection->localizedValue('title', $locale),
            'description'     => $collection->localizedValue('description', $locale),
            'hero_kicker'     => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle'   => $collection->localizedValue('hero_subtitle', $locale),
            'hero_image'      => $this->resolveImage($collection->hero_image),
            'hero_cta_label'  => $collection->localizedValue('hero_cta_label', $locale),
            'hero_cta_url'    => $collection->localizedValue('hero_cta_url', $locale),
            'content'         => $collection->localizedValue('content', $locale),
            'seo_title'       => $collection->localizedValue('seo_title', $locale),
            'seo_description' => $collection->localizedValue('seo_description', $locale),
            'starts_at'       => $collection->starts_at?->toIso8601String(),
            'ends_at'         => $collection->ends_at?->toIso8601String(),
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
        string $locale
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

        $query = Product::query()
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest();

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
                'filters' => $this->buildFilters($items),
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
