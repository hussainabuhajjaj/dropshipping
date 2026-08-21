<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Product;
use App\Services\Pricing\ProductCompareAtService as CompareAtService;
use App\Services\Storefront\HomeBuilderService;
use App\Support\ResolvesStorefrontVariantLabels;
use App\Support\StorefrontSpecs;

trait TransformsProducts
{
    use ResolvesStorefrontVariantLabels;

    protected ?CompareAtService $compareAtService = null;

    protected function transformProduct(Product $product, bool $includeMeta = false): array
    {
        $homeBuilder = app(HomeBuilderService::class);
        $media = $product->images?->sortBy('position')->pluck('url')->values()->all() ?? [];
        $media = collect($media)
            ->map(fn ($url) => $homeBuilder->normalizeImage(is_string($url) ? $url : null))
            ->filter()
            ->values()
            ->all();
        $locale = app()->getLocale();
        $variants = collect($product->variants ?? []);
        $variantPayload = $variants->map(function ($variant) use ($locale, $product, $homeBuilder) {
            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translations = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];
            $localizedTitle = $translations[$locale]['title'] ?? null;
            $fullTitle = $localizedTitle ?: $variant->title;
            $displayTitle = $this->resolveVariantDisplayTitle($variant, $fullTitle, $product->name);
            $variantImage = $homeBuilder->normalizeImage($this->resolveStorefrontVariantImage($variant));
            $price = $variant->price !== null ? (float) $variant->price : null;
            $compareAt = $variant->compare_at_price !== null ? (float) $variant->compare_at_price : null;
            $referencePrice = $this->compareAtService()->referencePrice(
                $price,
                $product->selling_price !== null ? (float) $product->selling_price : null
            );

            return [
                'id' => $variant->id,
                'title' => $displayTitle,
                'full_title' => $fullTitle,
                'display_title' => $displayTitle,
                'options' => $this->normalizeStorefrontVariantOptions($variant),
                'variant_image' => $variantImage,
                'price' => $price ?? 0.0,
                'compare_at_price' => $this->displayCompareAt($referencePrice, $compareAt),
                'sku' => $variant->sku,
                'currency' => $variant->currency ?? $product->currency ?? 'USD',
                'cj_vid' => $variant->cj_vid,
                'stock_on_hand' => $variant->stock_on_hand,
                'low_stock_threshold' => $variant->low_stock_threshold,
            ];
        })->values()->all();

        $defaultVariant = $variantPayload[0] ?? null;
        $price = $defaultVariant['price'] ?? (float) ($product->selling_price ?? 0);
        $currency = $defaultVariant['currency'] ?? $product->currency ?? 'USD';

        $wishlist = collect(session('wishlist', []));

        $translation = $product->translationForLocale($locale);
        $categoryName = $product->category?->name;
        if ($product->category && method_exists($product->category, 'translatedValue')) {
            $categoryName = $product->category->translatedValue('name', $locale);
        }
        $categorySlug = $product->category?->slug;
        $categoryHref = $categorySlug
            ? '/categories/' . urlencode((string) $categorySlug)
            : ($product->category_id ? '/products?category=' . urlencode((string) $product->category_id) : '/products');

        $rating = $product->reviews_avg_rating !== null
            ? (float) $product->reviews_avg_rating
            : (float) ($product->rating ?? 0);
        $ratingCount = $product->reviews_count !== null
            ? (int) $product->reviews_count
            : 0;

        $data = [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $translation?->name ?: $product->name,
            'code' => $product->code,
            'category' => $categoryName,
            'category_id' => $product->category_id,
            'category_slug' => $categorySlug,
            'category_href' => $categoryHref,
            'description' => $translation?->description ?: $product->description,
            'media' => $media,
            'image' => $media[0] ?? null,
            'videos' => $product->cj_video_urls ?? [],
            'is_active' => (bool) $product->is_active,
            'rating' => round($rating, 1),
            'rating_count' => $ratingCount,
            'variants' => $variantPayload,
            'default_variant_id' => $defaultVariant['id'] ?? null,
            'primary_variant_title' => $defaultVariant['title'] ?? null,
            'price' => $price,
            'compare_at_price' => $defaultVariant['compare_at_price'] ?? null,
            'currency' => $currency,
            'is_in_wishlist' => $wishlist->contains($product->id),
            'href' => route('products.show', $product, false),
            'url' => route('products.show', $product, false),
            'shipping_estimate' => [
                'cost' => (float) (is_array($product->pricing_meta) ? ($product->pricing_meta['cj_shipping'] ?? 0) : 0),
                'days' => $product->shipping_estimate_days,
            ],
        ];

        if ($includeMeta) {
            $data['variants'] = $variantPayload;
            $data['lead_time_days'] = $product->shipping_estimate_days;
            $data['specs'] = StorefrontSpecs::fromAttributes(is_array($product->attributes) ? $product->attributes : []);
            $data['meta_title'] = $product->meta_title;
            $data['meta_description'] = $product->meta_description;
        }

        return $data;
    }

    protected function displayCompareAt(?float $price, ?float $compareAt): ?float
    {
        return $this->compareAtService()->isDisplayWorthy($price, $compareAt)
            ? round((float) $compareAt, 2)
            : null;
    }

    protected function compareAtService(): CompareAtService
    {
        return $this->compareAtService ??= app(CompareAtService::class);
    }

    protected function normalizeStorefrontVariantOptions(mixed $variant): ?array
    {
        $options = is_array($variant->options ?? null) ? $variant->options : [];
        $properties = is_array($options['properties'] ?? null) ? $options['properties'] : [];
        $merged = [...$properties];

        foreach ($options as $key => $value) {
            if ($key === 'properties' || ! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }

            $merged[(string) $key] = $normalized;
        }

        return $merged === [] ? null : $merged;
    }

    protected function resolveStorefrontVariantImage(mixed $variant): ?string
    {
        if (is_string($variant->variant_image ?? null) && trim($variant->variant_image) !== '') {
            return trim($variant->variant_image);
        }

        $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];

        foreach ((array) data_get($metadata, 'raw.ae_sku_property_dtos', []) as $property) {
            if (! is_array($property)) {
                continue;
            }

            $image = $property['sku_image'] ?? $property['image_url'] ?? null;
            if (is_string($image) && trim($image) !== '') {
                return trim($image);
            }
        }

        return null;
    }

}
