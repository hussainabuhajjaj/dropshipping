<?php

namespace App\Domain\Products\Services;

use App\Domain\Products\DTOs\PricingResultDTO;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Models\LocalWareHouse;
use App\Services\Currency\CurrencyConversionService;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class PricingService
{
    private readonly CurrencyConversionService $currencyService;

    public function __construct(
        private readonly float $minMarginPercent = 0,
        private readonly float $maxDiscountPercent = 0,
        ?CurrencyConversionService $currencyService = null
    ) {
        $this->currencyService = $currencyService ?? app(CurrencyConversionService::class);
    }

    public static function makeFromConfig(): self
    {
        return new self(
            minMarginPercent: (float) config('pricing.min_margin_percent', 45),
            maxDiscountPercent: (float) config('pricing.max_discount_percent', 30),
        );
    }

    public static function usesNewEngine(array $context = []): bool
    {
        if (! (bool) config('pricing.use_new_engine', false)) {
            return false;
        }

        $rollout = (array) config('pricing.new_engine_rollout', []);
        $productId = isset($context['product_id']) && is_scalar($context['product_id']) ? (string) $context['product_id'] : null;
        $cjPid = isset($context['cj_pid']) && is_scalar($context['cj_pid']) ? trim((string) $context['cj_pid']) : null;
        $categoryId = isset($context['category_id']) && is_scalar($context['category_id']) ? (string) $context['category_id'] : null;

        $productIds = self::normalizeRolloutValues($rollout['product_ids'] ?? []);
        $cjPids = self::normalizeRolloutValues($rollout['cj_pids'] ?? []);
        $categoryIds = self::normalizeRolloutValues($rollout['category_ids'] ?? []);

        $hasExplicitScopes = $productIds !== [] || $cjPids !== [] || $categoryIds !== [];
        if ($hasExplicitScopes) {
            if ($productId !== null && in_array($productId, $productIds, true)) {
                return true;
            }

            if ($cjPid !== null && in_array($cjPid, $cjPids, true)) {
                return true;
            }

            if ($categoryId !== null && in_array($categoryId, $categoryIds, true)) {
                return true;
            }

            return false;
        }

        $percentage = max(0, min(100, (int) ($rollout['percentage'] ?? 100)));
        if ($percentage >= 100) {
            return true;
        }

        if ($percentage <= 0) {
            return false;
        }

        $rolloutKey = self::resolveRolloutKey($context);
        if ($rolloutKey === null) {
            return false;
        }

        $bucket = (crc32($rolloutKey) % 100) + 1;

        return $bucket <= $percentage;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private static function normalizeRolloutValues(array $values): array
    {
        return array_values(array_filter(array_map(static function (mixed $value): string {
            return is_scalar($value) ? trim((string) $value) : '';
        }, $values), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function resolveRolloutKey(array $context): ?string
    {
        foreach (['cj_pid', 'product_id', 'category_id'] as $key) {
            if (isset($context[$key]) && is_scalar($context[$key])) {
                $value = trim((string) $context[$key]);
                if ($value !== '') {
                    return "{$key}:{$value}";
                }
            }
        }

        return null;
    }

    public function calculate(
        float $productCost,
        float $weight,
        ?float $cjShipping = 0,
        ?LocalWareHouse $warehouse = null,
        string $currency = 'USD',
        array $options = []
    ): PricingResultDTO {
        if ($productCost <= 0) {
            throw new InvalidArgumentException('Product cost must be greater than 0');
        }

        $weightKg = max(0, $weight);
        $cjShippingAmount = max(0, (float) ($cjShipping ?? 0));

        $ratePerKg = $warehouse?->shipping_cost_per_kg
            ?? (float) config('pricing.default_shipping_per_kg', 7);

        if (! $warehouse || $warehouse->shipping_cost_per_kg === null) {
            Log::warning('Missing warehouse for pricing', [
                'warehouse_id' => $warehouse?->id,
                'requested_warehouse_id' => $options['warehouse_id'] ?? null,
                'fallback_rate_per_kg' => $ratePerKg,
                'currency' => $currency,
            ]);
        }

        $externalShipping = round($weightKg * $ratePerKg, 4);
        $landedCost = $productCost + $cjShippingAmount + $externalShipping;
        $marginUsed = $this->resolveMarginByWeight($weightKg);
        $sellingPrice = $this->calculateSellingPriceFromLandedCost($landedCost, $marginUsed, $currency);

        return new PricingResultDTO(
            costPrice: $productCost,
            weightKg: $weightKg,
            cjShipping: $cjShippingAmount,
            warehouseId: $warehouse?->id,
            shippingRatePerKg: $ratePerKg,
            externalShipping: $externalShipping,
            landedCost: $landedCost,
            basePrice: $sellingPrice,
            currency: $currency,
            marginPercent: $marginUsed * 100,
            pricingMeta: [
                'warehouse_id' => $warehouse?->id,
                'shipping_rate_per_kg' => $ratePerKg,
                'external_shipping' => round($externalShipping, 2),
                'cj_shipping' => round($cjShippingAmount, 2),
                'weight_kg' => round($weightKg, 4),
                'landed_cost' => round($landedCost, 2),
                'margin_used' => $marginUsed,
                'margin_source' => 'weight_based',
            ],
        );
    }

    public function resolveMarginByWeight(float $weightKg): float
    {
        $sanitizedWeight = max(0, $weightKg);
        $rules = (array) config('pricing.weight_margins', []);

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $max = $rule['max'] ?? null;
            $margin = $this->normalizeConfiguredMargin($rule['margin'] ?? null);

            if ($margin === null) {
                continue;
            }

            if ($max === null || $sanitizedWeight <= (float) $max) {
                return $margin;
            }
        }

        return $this->normalizeConfiguredMargin(config('pricing.default_margin', 0.35)) ?? 0.35;
    }

    public function calculateSellingPriceFromLandedCost(float $landedCost, float $margin, string $currency = 'USD'): float
    {
        if ($landedCost <= 0) {
            throw new InvalidArgumentException('Landed cost must be greater than 0');
        }

        $normalizedMargin = max(0, $margin);

        return $this->roundForCurrency($landedCost * (1 + $normalizedMargin), $currency);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function previewPricing(array $data): array
    {
        $result = $this->calculate(
            productCost: max(0, (float) ($data['product_cost'] ?? 0)),
            weight: max(0, (float) ($data['weight_kg'] ?? 0)),
            cjShipping: max(0, (float) ($data['cj_shipping'] ?? 0)),
            warehouse: (($data['warehouse'] ?? null) instanceof LocalWareHouse) ? $data['warehouse'] : null,
            currency: (string) ($data['currency'] ?? 'USD'),
            options: is_array($data['options'] ?? null) ? $data['options'] : [],
        );

        return [
            'cost_price' => $result->costPrice,
            'weight_kg' => $result->weightKg,
            'cj_shipping' => $result->cjShipping,
            'external_shipping' => $result->externalShipping,
            'landed_cost' => $result->landedCost,
            'selling_price' => $result->basePrice,
            'margin_percent' => $result->marginPercent,
            'pricing_meta' => $result->pricingMeta,
        ];
    }

    /**
     * Calculate comprehensive base price with all costs included (no shipping)
     */
    public function calculateBasePrice(
        float $supplierCost,
        string $supplierCurrency,
        ?float $platformFeePercent = null,
        ?float $paymentFeePercent = null
    ): array {
        // DEFENSIVE: Cost integrity validation
        if ($supplierCost <= 0) {
            throw new InvalidArgumentException('Supplier cost must be greater than 0');
        }
        
        if ($supplierCost > 10000) { // $10,000 is unusually high for dropshipping
            Log::warning('Unusually high supplier cost detected', [
                'supplier_cost' => $supplierCost,
                'supplier_currency' => $supplierCurrency
            ]);
        }

        // DEFENSIVE: Use supplier currency directly, don't convert unless needed
        $localCurrency = $supplierCurrency; // Keep in original currency
        $localCost = $supplierCost;
        
        Log::info('Using supplier currency directly', [
            'supplier_currency' => $supplierCurrency,
            'local_currency' => $localCurrency,
            'amount' => $supplierCost
        ]);

        // Apply currency buffer for volatility protection (only for XOF)
        $currencyBuffer = 0;
        $bufferedCost = $localCost;
        
        if ($localCurrency === 'XOF') {
            $currencyBuffer = (float) config('pricing.currency.xof_buffer_percent', 5);
            $bufferedCost = $localCost * (1 + $currencyBuffer / 100);
        }

        // Add platform and payment fees
        $platformFeeRate = $platformFeePercent ?? (float) config('pricing.fees.platform', 5.0);
        $paymentFeeRate = $paymentFeePercent ?? (float) config('pricing.fees.payment_gateway', 3.5);
        
        $feesMultiplier = 1 + ($platformFeeRate + $paymentFeeRate) / 100;
        $totalCost = $bufferedCost * $feesMultiplier;

        // DEFENSIVE: Total cost validation
        if ($totalCost < 1) {
            Log::warning('Total cost below minimum threshold', [
                'total_cost' => $totalCost,
                'supplier_cost' => $supplierCost
            ]);
        }

        return [
            'supplier_cost' => $supplierCost,
            'supplier_currency' => $supplierCurrency,
            'local_cost' => $localCost,
            'local_currency' => $localCurrency,
            'currency_buffer_amount' => $bufferedCost - $localCost,
            'platform_fee_amount' => $totalCost - $bufferedCost,
            'total_cost' => $totalCost,
            'currency' => $localCurrency,
        ];
    }

    /**
     * Calculate the minimum allowed selling price based on cost and margin (no shipping buffer).
     */
    public function minSellingPrice(float $cost, string $currency = 'USD'): float
    {
        if ($cost < 0) {
            throw new InvalidArgumentException('Cost price cannot be negative.');
        }

        // DEFENSIVE: Cost validation
        if ($cost <= 0) {
            Log::warning('Zero or negative cost in minSellingPrice', ['cost' => $cost]);
            return 0.0;
        }

        // DEFENSIVE: Use same currency as cost, don't convert
        $calculation = $this->calculateBasePrice($cost, $currency);
        
        $marginMultiplier = 1 + $this->minMarginPercent / 100;
        $minPrice = $calculation['total_cost'] * $marginMultiplier;

        // DEFENSIVE: Margin validation
        $actualMargin = (($minPrice - $cost) / $cost) * 100;
        if ($actualMargin > 500) { // 500% is suspicious
            Log::warning('Excessive margin calculated in minSellingPrice', [
                'cost' => $cost,
                'currency' => $currency,
                'min_price' => $minPrice,
                'margin_percent' => $actualMargin
            ]);
        }

        return $this->roundForCurrency($minPrice, $currency);
    }

    public function minimumPriceForProduct(Product $product, ?float $costOverride = null): float
    {
        $cost = $costOverride ?? (is_numeric($product->cost_price) ? (float) $product->cost_price : 0.0);
        $currency = (string) ($product->currency ?: 'USD');
        $pricingMeta = is_array($product->pricing_meta ?? null) ? $product->pricing_meta : [];
        $warehouseId = is_numeric($product->local_warehouse_id ?? null) ? (int) $product->local_warehouse_id : null;

        return $this->minimumPriceForContext($cost, $currency, $pricingMeta, $warehouseId);
    }

    public function minimumPriceForVariant(ProductVariant $variant, ?Product $product = null, ?float $costOverride = null): float
    {
        $product ??= $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();

        $cost = $costOverride ?? (is_numeric($variant->cost_price) ? (float) $variant->cost_price : 0.0);
        $currency = (string) ($variant->currency ?: $product?->currency ?: 'USD');
        $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
        $pricingMeta = is_array($metadata['pricing_meta'] ?? null) ? $metadata['pricing_meta'] : [];
        $warehouseId = is_numeric($metadata['local_warehouse_id'] ?? null)
            ? (int) $metadata['local_warehouse_id']
            : (is_numeric($product?->local_warehouse_id ?? null) ? (int) $product->local_warehouse_id : null);

        if (! isset($pricingMeta['weight_kg']) && is_numeric($variant->weight_grams ?? null) && (float) $variant->weight_grams > 0) {
            $pricingMeta['weight_kg'] = ((float) $variant->weight_grams) / 1000;
        }

        if (! isset($pricingMeta['cj_shipping']) && $product && is_array($product->pricing_meta ?? null)) {
            $pricingMeta['cj_shipping'] = $product->pricing_meta['cj_shipping'] ?? null;
        }

        return $this->minimumPriceForContext($cost, $currency, $pricingMeta, $warehouseId);
    }

    /**
     * Calculate selling price with margin and optional category multiplier.
     */
    public function calculateSellingPrice(
        float $cost,
        string $currency,
        ?float $marginPercent = null,
        ?int $categoryId = null,
        ?float $platformFeePercent = null,
        ?float $paymentFeePercent = null
    ): array {
        // DEFENSIVE: Input validation
        if ($cost <= 0) {
            throw new InvalidArgumentException('Cost must be greater than 0');
        }

        $margin = $marginPercent ?? $this->minMarginPercent;
        
        // DEFENSIVE: Margin range validation
        if ($margin < 0 || $margin > 500) {
            throw new InvalidArgumentException('Margin percent must be between 0 and 500');
        }

        $calculation = $this->calculateBasePrice($cost, $currency, $platformFeePercent, $paymentFeePercent);
        
        // Apply margin
        $marginMultiplier = 1 + $margin / 100;
        $basePrice = $calculation['total_cost'] * $marginMultiplier;

        // Apply category multiplier if applicable
        $categoryMultiplier = $this->getCategoryMultiplier($categoryId);
        $finalPrice = $basePrice * $categoryMultiplier;

        // DEFENSIVE: Final price validation
        $priceToCostRatio = $finalPrice / $cost;
        if ($priceToCostRatio > 50) { // More than 50x markup is suspicious
            Log::warning('Suspicious price-to-cost ratio detected', [
                'cost' => $cost,
                'final_price' => $finalPrice,
                'ratio' => $priceToCostRatio,
                'margin' => $margin,
                'category_multiplier' => $categoryMultiplier
            ]);
        }

        $roundedPrice = $this->roundForCurrency($finalPrice, $currency);

        return [
            'cost_price' => $cost,
            'base_price' => $roundedPrice,
            'currency' => $currency,
            'margin_percent' => $margin,
            'category_id' => $categoryId,
            'category_multiplier' => $categoryMultiplier,
            'total_cost' => $calculation['total_cost'],
            'platform_fee_percent' => $platformFeePercent ?? config('pricing.fees.platform', 5.0),
            'payment_fee_percent' => $paymentFeePercent ?? config('pricing.fees.payment_gateway', 3.5),
            'profit_amount' => $roundedPrice - $calculation['total_cost'],
            'profit_margin' => (($roundedPrice - $calculation['total_cost']) / $calculation['total_cost']) * 100,
        ];
    }

    /**
     * Update product pricing with comprehensive validation.
     */
    public function updateProductPricing(
        \App\Domain\Products\Models\Product $product,
        ?float $marginPercent = null,
        ?float $platformFeePercent = null,
        ?float $paymentFeePercent = null
    ): array {
        // DEFENSIVE: Product validation
        if (!$product->cost_price || $product->cost_price <= 0) {
            throw new InvalidArgumentException('Product must have a valid cost price');
        }

        // Use product's currency, don't convert
        $currency = $product->currency ?? 'USD';
        
        $result = $this->calculateSellingPrice(
            cost: $product->cost_price,
            currency: $currency,
            marginPercent: $marginPercent,
            categoryId: $product->category_id,
            platformFeePercent: $platformFeePercent,
            paymentFeePercent: $paymentFeePercent
        );

        $this->validateMinimumProfit($result);

        // DEFENSIVE: Don't overwrite currency field
        $product->update([
            'selling_price' => $result['base_price'],
            // Note: currency field is NOT updated to preserve supplier currency
        ]);

        // Update variants if they exist
        foreach ($product->variants as $variant) {
            if ($variant->cost_price && $variant->cost_price > 0) {
                $variantResult = $this->calculateSellingPrice(
                    cost: $variant->cost_price,
                    currency: $currency, // Use same currency as product
                    marginPercent: $marginPercent,
                    categoryId: $product->category_id,
                    platformFeePercent: $platformFeePercent,
                    paymentFeePercent: $paymentFeePercent
                );

                $variant->update([
                    'price' => $variantResult['base_price'],
                    'currency' => $currency, // Keep variant currency consistent
                ]);
            }
        }

        return $result;
    }

    /**
     * Get category-specific multiplier.
     */
    private function getCategoryMultiplier(?int $categoryId): float
    {
        $multipliers = config('pricing.category_multipliers', []);

        if (!$categoryId) {
            return (float) ($multipliers['default'] ?? 1.0);
        }

        return (float) ($multipliers[$categoryId] ?? $multipliers['default'] ?? 1.0);
    }

    /**
     * Validate minimum profit requirements.
     */
    private function validateMinimumProfit(array $calculation): void
    {
        $minimumProfitMargin = (float) config('pricing.minimum_profit_margin', 15.0);
        
        if ($calculation['profit_margin'] < $minimumProfitMargin) {
            throw new InvalidArgumentException(
                "Profit margin {$calculation['profit_margin']}% is below minimum {$minimumProfitMargin}%"
            );
        }
    }

    /**
     * Round amount based on currency precision.
     */
    private function roundForCurrency(float $amount, string $currency): float
    {
        $decimals = config('currency.decimals', []);
        $precision = isset($decimals[$currency]) ? (int) $decimals[$currency] : 2;
        return round($amount, $precision);
    }

    private function normalizeConfiguredMargin(mixed $margin): ?float
    {
        if (! is_numeric($margin)) {
            return null;
        }

        $value = (float) $margin;

        if ($value < 0) {
            return 0.0;
        }

        if ($value > 1) {
            return $value / 100;
        }

        return $value;
    }

    private function minimumPriceForContext(float $cost, string $currency, array $pricingMeta = [], ?int $warehouseId = null): float
    {
        if ($cost <= 0) {
            return $this->minSellingPrice($cost, $currency);
        }

        $weightKg = $this->normalizeNumeric($pricingMeta['weight_kg'] ?? null);
        $cjShipping = $this->normalizeNumeric($pricingMeta['cj_shipping'] ?? null) ?? 0.0;

        if ($weightKg !== null) {
            $warehouse = $warehouseId ? LocalWareHouse::query()->find($warehouseId) : null;

            return $this->calculate(
                productCost: $cost,
                weight: $weightKg,
                cjShipping: $cjShipping,
                warehouse: $warehouse,
                currency: $currency,
                options: ['warehouse_id' => $warehouseId]
            )->basePrice;
        }

        return $this->minSellingPrice($cost, $currency);
    }

    private function normalizeNumeric(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Validate a selling price against rules.
     */
    public function validatePrice(float $cost, float $selling, string $currency = 'USD'): void
    {
        $min = $this->minSellingPrice($cost, $currency);
        
        if ($selling < $min) {
            throw new InvalidArgumentException("Selling price {$selling} is below minimum {$min}");
        }

        // DEFENSIVE: Price-to-cost ratio validation
        $ratio = $selling / $cost;
        if ($ratio > 50) { // Configurable threshold
            throw new InvalidArgumentException("Price-to-cost ratio {$ratio} exceeds maximum threshold");
        }
    }
}
