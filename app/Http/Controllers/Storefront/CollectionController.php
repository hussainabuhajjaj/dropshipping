<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\StorefrontCollection;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    use TransformsProducts;

    public function index(): Response
    {
        $locale = app()->getLocale();

        $collections = StorefrontCollection::query()
            ->orderBy('display_order')
            ->get()
            ->filter(fn (StorefrontCollection $collection) => $collection->isActiveForLocale($locale))
            ->values();

        $grouped = $collections
            ->groupBy('type')
            ->map(fn ($items) => $items->map(fn (StorefrontCollection $collection) => $this->transformCollectionSummary($collection, $locale))->values())
            ->all();

        return Inertia::render('Collections/Index', [
            'collections' => $grouped,
            'locale' => $locale,
        ]);
    }

    public function show(StorefrontCollection $collection): Response
    {
        $locale = app()->getLocale();
        abort_if(! $collection->isActiveForLocale($locale), 404);

        $products = $collection
            ->resolveProducts($locale)
            ->map(fn (Product $product) => $this->transformProduct($product));

        return Inertia::render('Collections/Show', [
            'collection' => $this->transformCollectionDetail($collection, $locale),
            'products' => $products,
        ]);
    }

    private function transformCollectionSummary(StorefrontCollection $collection, ?string $locale): array
    {
        return [
            'id' => $collection->id,
            'title' => $collection->localizedValue('title', $locale),
            'slug' => $collection->slug,
            'type' => $collection->type,
            'description' => $collection->localizedValue('description', $locale),
            'hero_kicker' => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle' => $collection->localizedValue('hero_subtitle', $locale),
            'hero_image' => $collection->hero_image ? $this->resolveImagePath($collection->hero_image) : null,
            'starts_at' => $collection->starts_at,
            'ends_at' => $collection->ends_at,
        ];
    }

    private function transformCollectionDetail(StorefrontCollection $collection, ?string $locale): array
    {
        return [
            'id' => $collection->id,
            'title' => $collection->localizedValue('title', $locale),
            'slug' => $collection->slug,
            'type' => $collection->type,
            'description' => $collection->localizedValue('description', $locale),
            'hero_kicker' => $collection->localizedValue('hero_kicker', $locale),
            'hero_subtitle' => $collection->localizedValue('hero_subtitle', $locale),
            'hero_image' => $collection->hero_image ? $this->resolveImagePath($collection->hero_image) : null,
            'hero_cta_label' => $collection->localizedValue('hero_cta_label', $locale),
            'hero_cta_url' => $collection->localizedValue('hero_cta_url', $locale),
            'content' => $collection->localizedValue('content', $locale),
            'seo_title' => $collection->localizedValue('seo_title', $locale),
            'seo_description' => $collection->localizedValue('seo_description', $locale),
            'starts_at' => $collection->starts_at,
            'ends_at' => $collection->ends_at,
        ];
    }

    private function resolveImagePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(\Storage::url($path));
    }
}
