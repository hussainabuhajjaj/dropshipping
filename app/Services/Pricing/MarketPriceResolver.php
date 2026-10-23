<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
use App\Support\StorefrontMarket;
use Illuminate\Support\Facades\Log;

/**
 * Market-aware price resolver (Phase 1).
 *
 * CI  → returns the stored prices untouched (products.selling_price /
 *       product_variants.price). The CI catalog is sacred: nothing here
 *       reads or rewrites those columns differently than before.
 *
 * UAE → computes a market price from the SAME persisted pricing inputs that
 *       PricingService used at import time (cost, cj_shipping, weight,
 *       margin tier, category multiplier) but WITHOUT the CI
 *       external_shipping component. Checkout freight for UAE is quoted
 *       separately via CJFreightService (CN -> AE), so it must never be
 *       double-counted into the product price.
 *
 * If a UAE price cannot be computed safely, this resolver FAILS EXPLICITLY
 * (throws) — callers must not silently fall back to the CI price, because
 * the CI price embeds CI shipping and would misprice UAE orders.
 */
class MarketPriceResolver
{
    public function __construct(private readonly PricingService $pricingService)
    {
    }

    /**
     * Resolve the effective unit price for a product in the current market.
     *
     * @param string|null $country ISO country code; null = resolve from storefront state.
     *
     * @throws \RuntimeException when a UAE price cannot be computed.
     */
    public function priceForProduct(Product $product, ?string $country = null): float
    {
        return $this->resolve($product, null, $country)['price'];
    }

    public function priceForVariant(ProductVariant $variant, Product $product, ?string $country = null): float
    {
        return $this->resolve($product, $variant, $country)['price'];
    }

    /**
     * @return array{price: float, currency: string, market: string}
     */
    public function resolve(Product $product, ?ProductVariant $variant = null, ?string $country = null): array
    {
        $country = strtoupper(trim($country ?? '')) !== ''
            ? strtoupper(trim($country))
            : StorefrontMarket::resolveCountry(request());
        $market = StorefrontMarket::marketForCountry($country);

        if ($market === StorefrontMarket::CI) {
            // Existing behavior, byte-for-byte: variant price first, then stored selling price.
            $stored = $variant?->price !== null
                ? (float) $variant->price
                : (float) ($product->selling_price ?? 0);

            return [
                'price' => $stored,
                'currency' => (string) ($variant?->currency ?? $product->currency ?? 'USD'),
                'market' => StorefrontMarket::CI,
            ];
        }

        return [
            'price' => $this->uaePrice($product, $variant),
            'currency' => (string) ($product->currency ?? 'USD'),
            'market' => StorefrontMarket::UAE,
        ];
    }

    /**
     * Compute the UAE product price WITHOUT the CI external_shipping.
     *
     * Reuses PricingService rules verbatim (weight margin tiers + category
     * multipliers) by delegating with warehouse = null and zero external
     * shipping contribution. cj_shipping (inbound component already embedded
     * in the CI landed cost) is carried through unchanged per existing meta
     * semantics — no new cost formula is invented.
     */
    public function uaePrice(Product $product, ?ProductVariant $variant = null): float
    {
        $meta = is_array($product->pricing_meta ?? null) ? $product->pricing_meta : [];

        $cost = $this->positiveFloat($variant?->cost_price) ?? $this->positiveFloat($product->cost_price);
        if ($cost === null) {
            throw new \RuntimeException("UAE price unavailable: product {$product->id} has no valid cost_price.");
        }

        $cjShipping = max(0.0, (float) ($meta['cj_shipping'] ?? 0));

        $weightKg = $this->resolveWeightKg($product, $variant, $meta);
        if ($weightKg === null) {
            throw new \RuntimeException("UAE price unavailable: product {$product->id} has no resolvable weight.");
        }

        $margin = $this->pricingService->resolveMarginByWeight($weightKg);
        $landedCost = $cost + $cjShipping; // deliberately NO external_shipping, NO XOF buffer (catalog currency is USD)

        $price = $this->pricingService->calculateSellingPriceFromLandedCost($landedCost, $margin, (string) ($product->currency ?? 'USD'));

        if ($product->category_id !== null) {
            // Apply the same category multiplier rule as calculate(); reuse the
            // persisted multiplier when present so historical drift is preserved.
            $multiplier = $this->positiveFloat($meta['category_multiplier'] ?? null)
                ?? $this->configCategoryMultiplier((int) $product->category_id);
            $decimals = (int) (config('currency.decimals.'.$product->currency) ?? 2);
            $price = round($price * $multiplier, $decimals);
        }

        if ($price <= 0) {
            throw new \RuntimeException("UAE price unavailable: computed non-positive price for product {$product->id}.");
        }

        Log::info('UAE market price resolved', [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'cost' => $cost,
            'cj_shipping' => $cjShipping,
            'weight_kg' => $weightKg,
            'margin' => $margin,
            'price' => $price,
        ]);

        return $price;
    }

    private function resolveWeightKg(Product $product, ?ProductVariant $variant, array $meta): ?float
    {
        $candidates = [
            $this->positiveFloat($variant?->weight_grams !== null ? ((float) $variant->weight_grams / 1000) : null),
            $this->positiveFloat($meta['weight_kg'] ?? null),
            $this->positiveFloat(data_get($product->attributes ?? [], 'cj_payload.productWeight') ?? null),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function configCategoryMultiplier(int $categoryId): float
    {
        $multipliers = (array) config('pricing.category_multipliers', []);

        return (float) ($multipliers[$categoryId] ?? $multipliers['default'] ?? 1.0);
    }

    private function positiveFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }
}
