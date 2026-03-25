<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
use RuntimeException;

class CurrencyConversionService
{
    private const PRICING_META_AMOUNT_KEYS = [
        'shipping_rate_per_kg',
        'external_shipping',
        'cj_shipping',
        'landed_cost',
    ];

    public function convertAmount(?float $amount, string $from = 'USD', string $to = 'XOF'): ?float
    {
        if ($amount === null) {
            return null;
        }

        $fromCode = $this->normalizeCurrency($from);
        $toCode = $this->normalizeCurrency($to);

        if ($fromCode === $toCode) {
            return $this->roundForCurrency($amount, $toCode);
        }

        $rate = $this->rate($fromCode, $toCode);
        return $this->roundForCurrency($amount * $rate, $toCode);
    }

    public function convertUsdToXaf(?float $amount): ?float
    {
        return $this->convertAmount($amount, 'USD', 'XAF');
    }

    public function normalize(string $code): string
    {
        return $this->normalizeCurrency($code);
    }

    public function convertProductPricesToXaf(Product $product, bool $includeVariants = true, bool $includeCost = true, bool $includeCompareAt = true): void
    {
        $productCurrency = $product->currency ?? config('currency.base', 'USD');
        $fromCurrency = $this->normalizeCurrency($productCurrency);
        $toCurrency = $this->normalizeCurrency('XAF');

        if ($fromCurrency === $toCurrency) {
            return;
        }

        $product->selling_price = $this->convertAmount(
            $this->toNullableFloat($product->selling_price),
            $fromCurrency,
            $toCurrency
        );

        if ($includeCost) {
            $product->cost_price = $this->convertAmount(
                $this->toNullableFloat($product->cost_price),
                $fromCurrency,
                $toCurrency
            );
        }

        if (PricingService::usesNewEngine([
            'product_id' => $product->id,
            'cj_pid' => $product->cj_pid,
            'category_id' => $product->category_id,
        ])) {
            $product->pricing_meta = $this->convertPricingMeta($product->pricing_meta, $fromCurrency, $toCurrency);
        }

        $product->currency = $toCurrency;
        $product->save();

        if (! $includeVariants) {
            return;
        }

        $product->loadMissing('variants');
        foreach ($product->variants ?? [] as $variant) {
            $this->convertVariantPricesToXaf($variant, $fromCurrency, $includeCost, $includeCompareAt);
        }
    }

    public function convertVariantPricesToXaf(ProductVariant $variant, ?string $fallbackFrom = null, bool $includeCost = true, bool $includeCompareAt = true): void
    {
        $fromCurrency = $this->normalizeCurrency($variant->currency ?? $fallbackFrom ?? config('currency.base', 'USD'));
        $toCurrency = $this->normalizeCurrency('XAF');

        if ($fromCurrency === $toCurrency) {
            return;
        }

        $variant->price = $this->convertAmount($this->toNullableFloat($variant->price), $fromCurrency, $toCurrency);

        if ($includeCompareAt) {
            $variant->compare_at_price = $this->convertAmount(
                $this->toNullableFloat($variant->compare_at_price),
                $fromCurrency,
                $toCurrency
            );
        }

        if ($includeCost) {
            $variant->cost_price = $this->convertAmount(
                $this->toNullableFloat($variant->cost_price),
                $fromCurrency,
                $toCurrency
            );
        }

        $product = $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();
        if ($product && PricingService::usesNewEngine([
            'product_id' => $product->id,
            'cj_pid' => $product->cj_pid,
            'category_id' => $product->category_id,
        ])) {
            $metadata = is_array($variant->metadata) ? $variant->metadata : [];
            if (isset($metadata['pricing_meta']) && is_array($metadata['pricing_meta'])) {
                $metadata['pricing_meta'] = $this->convertPricingMeta($metadata['pricing_meta'], $fromCurrency, $toCurrency);
            }
            $variant->metadata = $metadata;
        }

        $variant->currency = $toCurrency;
        $variant->save();
    }

    public function rate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $rates = config('currency.rates', []);
        $directKey = "{$from}_{$to}";
        $inverseKey = "{$to}_{$from}";

        if (isset($rates[$directKey]) && is_numeric($rates[$directKey])) {
            $rate = (float) $rates[$directKey];
            if ($rate <= 0) {
                throw new RuntimeException("FX rate {$directKey} must be greater than zero.");
            }
            return $rate;
        }

        if (isset($rates[$inverseKey]) && is_numeric($rates[$inverseKey])) {
            $rate = (float) $rates[$inverseKey];
            if ($rate <= 0) {
                throw new RuntimeException("FX rate {$inverseKey} must be greater than zero.");
            }
            return 1 / $rate;
        }

        throw new RuntimeException("FX rate for {$from} -> {$to} is not configured.");
    }

    private function normalizeCurrency(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $aliases = config('currency.aliases', []);
        if (isset($aliases[$normalized])) {
            return strtoupper((string) $aliases[$normalized]);
        }

        return $normalized;
    }

    private function roundForCurrency(float $amount, string $currency): float
    {
        $decimals = config('currency.decimals', []);
        $precision = isset($decimals[$currency]) ? (int) $decimals[$currency] : 2;
        return round($amount, $precision);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }

    /**
     * @param array<string, mixed>|null $meta
     * @return array<string, mixed>|null
     */
    private function convertPricingMeta(?array $meta, string $fromCurrency, string $toCurrency): ?array
    {
        if (! is_array($meta) || $meta === []) {
            return $meta;
        }

        foreach (self::PRICING_META_AMOUNT_KEYS as $key) {
            if (array_key_exists($key, $meta) && is_numeric($meta[$key])) {
                $meta[$key] = $this->convertAmount((float) $meta[$key], $fromCurrency, $toCurrency);
            }
        }

        if (array_key_exists('currency', $meta) && is_scalar($meta['currency'])) {
            $meta['currency'] = $toCurrency;
        }

        return $meta;
    }
}
