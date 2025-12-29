<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront\Concerns;

use App\Domain\Products\Models\Product;

trait TransformsProducts
{
    protected function transformProduct(Product $product, bool $includeMeta = false): array
    {
        $media = $product->images?->sortBy('position')->pluck('url')->values()->all() ?? [];
        $locale = app()->getLocale();
        $variants = collect($product->variants ?? []);
        $variantPayload = $variants->map(function ($variant) use ($locale, $product) {
            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translations = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];
            $localizedTitle = $translations[$locale]['title'] ?? null;

            return [
                'id' => $variant->id,
                'title' => $localizedTitle ?: $variant->title,
                'price' => (float) ($variant->price ?? 0),
                'compare_at_price' => $variant->compare_at_price !== null ? (float) $variant->compare_at_price : null,
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

        $data = [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $translation?->name ?: $product->name,
            'category' => $product->category?->name,
            'description' => $translation?->description ?: $product->description,
            'media' => $media,
            'videos' => $product->cj_video_urls ?? [],
            'is_active' => (bool) $product->is_active,
            'rating' => round((float) ($product->reviews_avg_rating ?? 0), 1),
            'rating_count' => (int) ($product->reviews_count ?? 0),
            'variants' => $variantPayload,
            'default_variant_id' => $defaultVariant['id'] ?? null,
            'primary_variant_title' => $defaultVariant['title'] ?? null,
            'price' => $price,
            'compare_at_price' => $defaultVariant['compare_at_price'] ?? null,
            'currency' => $currency,
            'is_in_wishlist' => $wishlist->contains($product->id),
        ];

        if ($includeMeta) {
            $data['variants'] = $variantPayload;
            $data['lead_time_days'] = $product->shipping_estimate_days;
            $data['specs'] = is_array($product->attributes) ? $product->attributes : [];
            $data['meta_title'] = $product->meta_title;
            $data['meta_description'] = $product->meta_description;
        }

        return $data;
    }
}
