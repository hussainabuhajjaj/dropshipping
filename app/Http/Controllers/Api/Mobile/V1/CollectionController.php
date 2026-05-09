<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\ProductResource;
use App\Models\Category;
use App\Models\StorefrontCollection;
use App\Services\Storefront\MobileCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CollectionController extends ApiController
{
    public function __construct(
        private readonly MobileCollectionService $mobileCollectionService,
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
        $items = collect();
        $productPayload = [];
        $filters = $collection->availableFilters($locale);
        $total = 0;
        $lastPage = 1;

        try {
            $resolvedProducts = $collection->paginateFilteredProducts(
                $requestFilters,
                $locale,
                $perPage,
                $page
            );
            $items = collect($resolvedProducts->items())->values();
            $productPayload = ProductResource::collection($items)->resolve();
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
        }

        return $this->success(
            [
                'collection' => $this->transformDetail($collection, $locale),
                'products' => $productPayload,
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
        $items = collect();
        $productPayload = [];
        $filters = [
            'price_range' => [
                'min' => null,
                'max' => null,
            ],
            'attributeDefs' => [],
            'brands' => [],
        ];
        $total = 0;
        $lastPage = 1;

        try {
            $products = $this->mobileCollectionService->paginateLegacyCategory(
                $category,
                $locale,
                $requestFilters,
                $perPage,
                $page
            );
            $items = collect($products->items())->values();
            $productPayload = ProductResource::collection($items)->resolve();
            $total = $products->total();
            $lastPage = $products->lastPage();
        } catch (\Throwable $exception) {
            Log::error('Mobile legacy collection render failed', [
                'category_id' => $category->id,
                'category_slug' => $category->slug,
                'requested_slug' => $requestedSlug,
                'locale' => $locale,
                'filters' => $requestFilters,
                'error' => $exception->getMessage(),
            ]);
        }

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
                'products' => $productPayload,
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
}
