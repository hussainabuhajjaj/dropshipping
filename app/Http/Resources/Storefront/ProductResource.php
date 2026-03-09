<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use App\Domain\Products\Models\Product;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Storefront\HomeBuilderService;
use App\Support\ResolvesStorefrontVariantLabels;
use Illuminate\Http\Request;

class ProductResource extends JsonResource
{
    use ResolvesStorefrontVariantLabels;

    protected bool $includeMeta = false;

    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $homeBuilder = app(HomeBuilderService::class);
        $media = $product->images?->sortBy('position')->pluck('url')->values()->all() ?? [];
        $media = collect($media)
            ->map(fn ($url) => $homeBuilder->normalizeImage(is_string($url) ? $url : null))
            ->filter()
            ->values()
            ->all();
        $locale = app()->getLocale();
        $variants = collect($product->variants ?? []);
        $variantPayload = $variants->map(function ($variant) use ($locale, $product) {
            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translations = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];
            $localizedTitle = $translations[$locale]['title'] ?? null;
            $fullTitle = $localizedTitle ?: $variant->title;
            $displayTitle = $this->resolveVariantDisplayTitle($variant, $fullTitle, $product->name);

            return [
                'id' => $variant->id,
                'title' => $displayTitle,
                'full_title' => $fullTitle,
                'display_title' => $displayTitle,
                'options' => is_array($variant->options ?? null) ? $variant->options : null,
                'price' => (float) ($variant->price ?? 0),
                'compare_at_price' => $variant->compare_at_price !== null ? (float) $variant->compare_at_price : null,
                'sku' => $variant->sku,
                'currency' => $variant->currency ?? $product->currency ?? 'USD',
                'cj_vid' => $variant->cj_vid,
                'stock_on_hand' => $variant->stock_on_hand,
                'low_stock_threshold' => $variant->low_stock_threshold,
            ];
        })->values()->all();

        $pricedVariant = collect($variantPayload)
            ->filter(fn (array $variant) => $variant['price'] !== null)
            ->sortBy('price')
            ->first();

        $defaultVariant = $pricedVariant ?? $variantPayload[0] ?? null;
        $variantPrice = $defaultVariant['price'] ?? null;
        $sellingPrice = $product->selling_price !== null ? (float) $product->selling_price : null;

        if ($sellingPrice !== null && ($variantPrice === null || $sellingPrice < $variantPrice)) {
            $price = $sellingPrice;
            $currency = $product->currency ?? 'USD';
            $compareAt = null;
            $variantForDisplay = null;
        } else {
            $price = $variantPrice ?? (float) ($product->selling_price ?? 0);
            $currency = $defaultVariant['currency'] ?? $product->currency ?? 'USD';
            $compareAt = $defaultVariant['compare_at_price'] ?? null;
            $variantForDisplay = $defaultVariant;
        }

        $translation = $product->translationForLocale($locale);

        $categoryName = $product->category?->name;
        if ($product->category && method_exists($product->category, 'translatedValue')) {
            $categoryName = $product->category->translatedValue('name', $locale);
        }

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
            'category' => $categoryName,
            'category_id' => $product->category_id,
            'description' => $translation?->description ?: $product->description,
            'media' => $media,
            'videos' => $product->cj_video_urls ?? [],
            'is_active' => (bool) $product->is_active,
            'rating' => round($rating, 1),
            'rating_count' => $ratingCount,
            'variants' => $variantPayload,
            'default_variant_id' => $variantForDisplay['id'] ?? null,
            'primary_variant_title' => $variantForDisplay['title'] ?? null,
            'price' => $price,
            'compare_at_price' => $compareAt,
            'currency' => $currency,
            'is_in_wishlist' => false,
        ];

        // Disable backend conversion - handle it all on frontend
        // $converter = app(CurrencyConversionService::class);
        // $requestedCurrency = $this->resolveRequestedCurrency($request, $converter);
        // if ($requestedCurrency && $requestedCurrency !== $currency) {
        //     try {
        //         $data['price'] = $converter->convertAmount($data['price'], $currency, $requestedCurrency);
        //         $data['compare_at_price'] = $converter->convertAmount($data['compare_at_price'], $currency, $requestedCurrency);
        //         $data['currency'] = $requestedCurrency;

        //         $data['variants'] = collect($variantPayload)->map(function (array $variant) use ($converter, $requestedCurrency, $currency) {
        //             $baseCurrency = $variant['currency'] ?? $currency;
        //             $variant['price'] = $converter->convertAmount($variant['price'], $baseCurrency, $requestedCurrency);
        //             $variant['compare_at_price'] = $converter->convertAmount($variant['compare_at_price'], $baseCurrency, $requestedCurrency);
        //             $variant['currency'] = $requestedCurrency;
        //             return $variant;
        //         })->values()->all();
        //     } catch (\Throwable) {
        //         // Fall back to original currency when rate is not configured.
        //     }
        // }

        if ($this->includeMeta) {
            $data['lead_time_days'] = $product->shipping_estimate_days;
            $data['specs'] = is_array($product->attributes) ? $product->attributes : [];
            $data['meta_title'] = $product->meta_title;
            $data['meta_description'] = $product->meta_description;
        }

        return $data;
    }

    private function resolveRequestedCurrency(Request $request, CurrencyConversionService $converter): ?string
    {
        // Check session currency first (from SetUserPreferences middleware)
        $currency = session('user_currency');
        
        // Fall back to header/query/input methods
        $currency ??= $request->header('X-Currency')
            ?? $request->query('currency')
            ?? $request->input('currency');

        if (! $currency) {
            return null;
        }

        return $converter->normalize((string) $currency);
    }
}
