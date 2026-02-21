<?php

namespace App\Domain\Products\Services;

use App\Services\Currency\CurrencyConversionService;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Support\Facades\Log;

class PricingService
{
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
        if (!$categoryId) {
            return 1.0;
        }

        $multipliers = config('pricing.category_multipliers', []);
        return $multipliers[$categoryId] ?? 1.0;
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
