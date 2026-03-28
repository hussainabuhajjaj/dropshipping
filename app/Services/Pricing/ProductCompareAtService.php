<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Domain\Products\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

class ProductCompareAtService
{
    private const HARD_MAX_MULTIPLIER = 3.0;

    public function generate(Product $product, bool $force = false): void
    {
        $product->loadMissing(['variants', 'category']);
        $variants = collect($product->variants ?? []);
        $productSellingPrice = $this->toNullableFloat($product->selling_price ?? null);

        if ($variants->isEmpty()) {
            return;
        }

        $benchmarks = $this->buildBenchmarks($product->category_id);
        $minDiscount = $this->minDiscountPercent();
        $maxDiscount = $this->maxDiscountPercent();

        foreach ($variants as $variant) {
            $price = $this->toNullableFloat($variant->price ?? null);
            if ($price === null || $price <= 0) {
                continue;
            }
            $referencePrice = $this->referencePrice($price, $productSellingPrice);

            $existingCompareAt = $this->toNullableFloat($variant->compare_at_price ?? null);
            if (! $force && $this->isDisplayWorthy($referencePrice, $existingCompareAt)) {
                continue;
            }

            $targetDiscount = $this->resolveTargetDiscountPercent($referencePrice, $product->category_id, $benchmarks);
            $targetDiscount = max($minDiscount, min($targetDiscount, $maxDiscount));

            $compareAt = $this->compareAtFromDiscount($referencePrice, $targetDiscount);
            $minimumVisibleCompareAt = $this->minimumCompareAtForDiscount($referencePrice, $minDiscount);

            if ($compareAt < $minimumVisibleCompareAt) {
                $compareAt = $this->psychologicalRound($minimumVisibleCompareAt);
            }

            if ($compareAt <= $referencePrice || $compareAt > $referencePrice * self::HARD_MAX_MULTIPLIER) {
                continue;
            }

            $variant->compare_at_price = $compareAt;

            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            unset($metadata['compare_at_ai']);
            $metadata['compare_at_strategy'] = [
                'provider' => 'smart_rules',
                'generated_at' => now()->toDateTimeString(),
                'category_id' => $product->category_id,
                'target_discount_percent' => round($targetDiscount, 2),
                'minimum_visible_discount_percent' => round($minDiscount, 2),
                'benchmark_discount_percent' => $benchmarks['category']['avg_discount_percent']
                    ?? $benchmarks['global']['avg_discount_percent']
                    ?? null,
            ];
            $variant->metadata = $metadata;
            $variant->save();
        }
    }

    public function isDisplayWorthy(?float $price, ?float $compareAt): bool
    {
        if ($price === null || $compareAt === null || $price <= 0 || $compareAt <= $price) {
            return false;
        }

        $discountPercent = (($compareAt - $price) / $compareAt) * 100;

        return $discountPercent >= $this->minDiscountPercent();
    }

    public function referencePrice(?float $variantPrice, ?float $productSellingPrice = null): ?float
    {
        $prices = array_values(array_filter(
            [$variantPrice, $productSellingPrice],
            static fn ($value) => is_numeric($value) && (float) $value > 0
        ));

        if ($prices === []) {
            return null;
        }

        return (float) max($prices);
    }

    private function resolveTargetDiscountPercent(float $price, ?int $categoryId, array $benchmarks): float
    {
        $benchmarkDiscount = $benchmarks['category']['avg_discount_percent']
            ?? $benchmarks['global']['avg_discount_percent']
            ?? (float) config('pricing.compare_at.default_discount_percent', 18);

        $priceAdjustment = match (true) {
            $price < 10 => 4.0,
            $price < 25 => 2.0,
            $price >= 100 => -3.0,
            $price >= 50 => -1.5,
            default => 0.0,
        };

        $categoryAdjustment = 0.0;
        $multiplier = $this->categoryMultiplier($categoryId);
        if ($multiplier >= 1.2) {
            $categoryAdjustment = 2.0;
        } elseif ($multiplier <= 0.95) {
            $categoryAdjustment = -1.0;
        }

        return round($benchmarkDiscount + $priceAdjustment + $categoryAdjustment, 2);
    }

    private function compareAtFromDiscount(float $price, float $discountPercent): float
    {
        $discountFactor = max(0.01, 1 - ($discountPercent / 100));

        return $this->psychologicalRound($price / $discountFactor);
    }

    private function minimumCompareAtForDiscount(float $price, float $discountPercent): float
    {
        $discountFactor = max(0.01, 1 - ($discountPercent / 100));

        return round($price / $discountFactor, 2);
    }

    private function psychologicalRound(float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        if ($value < 100) {
            return round(ceil($value) - 0.01, 2);
        }

        return round((ceil($value / 5) * 5) - 0.01, 2);
    }

    private function buildBenchmarks(?int $categoryId): array
    {
        $baseQuery = ProductVariant::query()
            ->whereNotNull('compare_at_price')
            ->where('compare_at_price', '>', 0)
            ->where('price', '>', 0);

        $discountFactor = max(0.01, 1 - ($this->minDiscountPercent() / 100));
        $baseQuery->whereRaw('compare_at_price > price / ?', [$discountFactor]);

        $global = $this->statsForQuery(clone $baseQuery);
        $category = null;

        if ($categoryId !== null) {
            $category = $this->statsForQuery(
                (clone $baseQuery)->whereHas('product', fn (Builder $query) => $query->where('category_id', $categoryId))
            );
        }

        return [
            'category' => $category,
            'global' => $global,
        ];
    }

    private function statsForQuery(Builder $query): ?array
    {
        $aggregate = (clone $query)->selectRaw(
            'AVG(price) as avg_price,
            AVG(compare_at_price) as avg_compare_at_price,
            AVG((compare_at_price - price) / compare_at_price * 100) as avg_discount_percent,
            MIN(price) as min_price,
            MAX(price) as max_price'
        )->first();

        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        $avgPrice = $this->toNullableFloat($aggregate?->avg_price ?? null);
        $avgCompare = $this->toNullableFloat($aggregate?->avg_compare_at_price ?? null);
        $avgDiscount = $this->toNullableFloat($aggregate?->avg_discount_percent ?? null);
        $minPrice = $this->toNullableFloat($aggregate?->min_price ?? null);
        $maxPrice = $this->toNullableFloat($aggregate?->max_price ?? null);

        $multiplier = null;
        if ($avgPrice !== null && $avgPrice > 0 && $avgCompare !== null) {
            $multiplier = round($avgCompare / $avgPrice, 3);
        }

        return [
            'sample_size' => $count,
            'avg_price' => $avgPrice !== null ? round($avgPrice, 2) : null,
            'avg_compare_at_price' => $avgCompare !== null ? round($avgCompare, 2) : null,
            'avg_discount_percent' => $avgDiscount !== null ? round($avgDiscount, 2) : null,
            'avg_compare_at_multiplier' => $multiplier,
            'min_price' => $minPrice !== null ? round($minPrice, 2) : null,
            'max_price' => $maxPrice !== null ? round($maxPrice, 2) : null,
        ];
    }

    private function categoryMultiplier(?int $categoryId): float
    {
        $multipliers = config('pricing.category_multipliers', []);

        if ($categoryId !== null && isset($multipliers[$categoryId]) && is_numeric($multipliers[$categoryId])) {
            return (float) $multipliers[$categoryId];
        }

        return is_numeric($multipliers['default'] ?? null) ? (float) $multipliers['default'] : 1.0;
    }

    private function minDiscountPercent(): float
    {
        return (float) config('pricing.compare_at.min_discount_percent', 5);
    }

    private function maxDiscountPercent(): float
    {
        return (float) config('pricing.compare_at.max_discount_percent', 30);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }
}
