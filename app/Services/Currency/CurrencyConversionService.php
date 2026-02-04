<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use RuntimeException;

class CurrencyConversionService
{
    public function convertAmount(?float $amount, string $from = 'USD', string $to = 'XAF'): ?float
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
}
