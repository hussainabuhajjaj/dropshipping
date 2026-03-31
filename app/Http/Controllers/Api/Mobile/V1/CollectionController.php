<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\ProductResource;
use App\Models\StorefrontCollection;
use App\Services\Storefront\ProductMetaExtractor;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function show(Request $request, StorefrontCollection $collection): JsonResponse
    {
        $locale = app()->getLocale();

        abort_if(! $collection->isActiveForLocale($locale), 404);

        $perPage = min((int) ($request->query('per_page', 18)), 50);
        $page    = max((int) ($request->query('page', 1)), 1);

        $products = $collection->resolveProducts($locale);
        $filters = $this->buildFilters($products);

        $total   = $products->count();
        $items   = $products->forPage($page, $perPage)->values();

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
                'lastPage'    => (int) ceil($total / $perPage),
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
                if ($product->selling_price !== null && is_numeric($product->selling_price)) {
                    return (float) $product->selling_price;
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
}
