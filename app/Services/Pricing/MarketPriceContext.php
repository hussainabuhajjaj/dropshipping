<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Support\StorefrontMarket;

/**
 * Request-scoped market price context.
 *
 * Holds the destination country for the current storefront request (product
 * pages, listings, cart, checkout) and caches resolved prices per product /
 * variant so a single page render does not recompute repeatedly.
 *
 * When no country has been set explicitly, resolution falls back to the
 * minimal storefront state (session override -> ?country= -> default CI),
 * which preserves 100% of existing behavior for every visitor who has not
 * selected a market.
 */
final class MarketPriceContext
{
    private ?string $country = null;

    /** @var array<string, float> */
    private array $priceCache = [];

    public function __construct(private readonly MarketPriceResolver $resolver)
    {
    }

    public function setCountry(?string $country): void
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '') {
            return;
        }

        if ($this->country !== $country) {
            $this->country = $country;
            $this->priceCache = [];
        }
    }

    public function country(): string
    {
        return $this->country ?? StorefrontMarket::resolveCountry(request());
    }

    public function market(): string
    {
        return StorefrontMarket::marketForCountry($this->country());
    }

    public function isUae(): bool
    {
        return $this->market() === StorefrontMarket::UAE;
    }

    public function priceForProduct(Product $product): float
    {
        $key = 'p:'.$product->id.':'.$this->country();

        return $this->priceCache[$key] ??= $this->resolver->priceForProduct($product, $this->country());
    }

    public function priceForVariant(ProductVariant $variant, Product $product): float
    {
        $key = 'v:'.$variant->id.':'.$this->country();

        return $this->priceCache[$key] ??= $this->resolver->priceForVariant($variant, $product, $this->country());
    }

    /**
     * Resolve a variant price when the underlying resolver cannot compute a
     * UAE price (missing cost/weight metadata). Returns null instead of
     * throwing so listing/product-page rendering can degrade gracefully while
     * logging the reason. Checkout must use the strict methods instead.
     */
    public function tryPriceForVariant(ProductVariant $variant, Product $product): ?float
    {
        try {
            return $this->priceForVariant($variant, $product);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Market price unavailable for variant', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'country' => $this->country(),
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function tryPriceForProduct(Product $product): ?float
    {
        $key = 'p:'.$product->id.':'.$this->country();
        if (array_key_exists($key, $this->priceCache)) {
            return $this->priceCache[$key];
        }

        try {
            return $this->priceForProduct($product);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Market price unavailable for product', [
                'product_id' => $product->id,
                'country' => $this->country(),
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
