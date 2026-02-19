<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PricingService
{
    private const CACHE_TTL = 3600;
    private const LOCK_TTL = 30;

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
        // Convert supplier cost to local currency
        $localCurrency = config('pricing.currency.default', 'XOF');
        $localCost = $this->currencyService->convertAmount(
            $supplierCost,
            $supplierCurrency,
            $localCurrency
        );

        if ($localCost === null) {
            throw new RuntimeException('Failed to convert supplier cost to local currency');
        }

        // Apply currency buffer for volatility protection
        $currencyBuffer = (float) config('pricing.currency.xof_buffer_percent', 5);
        $bufferedCost = $localCost * (1 + $currencyBuffer / 100);

        // Add platform and payment fees
        $platformFeeRate = $platformFeePercent ?? (float) config('pricing.fees.platform', 5.0);
        $paymentFeeRate = $paymentFeePercent ?? (float) config('pricing.fees.payment_gateway', 3.5);
        
        $feesMultiplier = 1 + ($platformFeeRate + $paymentFeeRate) / 100;
        $totalCost = $bufferedCost * $feesMultiplier;

        return [
            'supplier_cost' => $supplierCost,
            'local_cost' => $localCost,
            'currency_buffer_amount' => $bufferedCost - $localCost,
            'platform_fee_amount' => $totalCost - $bufferedCost,
            'total_cost' => $totalCost,
            'currency' => $localCurrency,
        ];
    }

    /**
     * Calculate final selling price with margin and category multiplier
     */
    public function calculateSellingPrice(
        array $calculation,
        float $marginPercent,
        ?int $categoryId = null
    ): array {
        $categoryMultiplier = $this->getCategoryMultiplier($categoryId);
        
        // Apply margin
        $priceWithMargin = $calculation['total_cost'] * (1 + $marginPercent / 100);
        
        // Apply category multiplier
        $basePrice = $priceWithMargin * $categoryMultiplier;
        
        // Round to currency precision
        $finalPrice = $this->roundForCurrency($basePrice);

        // Calculate actual profit
        $actualProfit = $finalPrice - $calculation['total_cost'];
        $actualMarginPercent = $calculation['total_cost'] > 0 
            ? ($actualProfit / $calculation['total_cost']) * 100 
            : 0;

        return [
            'base_price' => $finalPrice,
            'total_cost' => $calculation['total_cost'],
            'profit_amount' => $actualProfit,
            'profit_margin_percent' => $actualMarginPercent,
            'currency' => $calculation['currency'],
        ];
    }

    /**
     * Apply marketing discounts safely with profit protection
     */
    public function applyMarketingDiscounts(
        array $baseResult,
        ?float $promotionDiscount = null,
        ?float $campaignDiscount = null,
        ?float $flashSaleDiscount = null,
        ?float $couponDiscount = null
    ): array {
        $currentPrice = $baseResult['base_price'];
        $totalDiscountPercent = 0;

        // Apply discounts in priority order
        $discounts = array_filter([
            ['type' => 'promotion', 'percent' => $promotionDiscount],
            ['type' => 'campaign', 'percent' => $campaignDiscount],
            ['type' => 'flash_sale', 'percent' => $flashSaleDiscount],
            ['type' => 'coupon', 'percent' => $couponDiscount],
        ]);

        foreach ($discounts as $discount) {
            $discountPercent = min($discount['percent'], $this->maxDiscountPercent);
            $discountAmount = $currentPrice * ($discountPercent / 100);
            
            // Check if this discount would violate minimum profit
            $newPrice = $currentPrice - $discountAmount;
            $newProfit = $newPrice - $baseResult['total_cost'];
            $minProfitMargin = (float) config('pricing.minimum_profit_margin', 15);
            $minProfitAmount = $baseResult['total_cost'] * ($minProfitMargin / 100);

            if ($newProfit < $minProfitAmount) {
                // Adjust discount to maintain minimum profit
                $maxAllowedDiscount = $currentPrice - ($baseResult['total_cost'] + $minProfitAmount);
                $discountAmount = min($discountAmount, $maxAllowedDiscount);
                $discountPercent = ($discountAmount / $currentPrice) * 100;
            }

            $totalDiscountPercent += $discountPercent;
            $currentPrice -= $discountAmount;
        }

        $actualDiscountPercent = ($baseResult['base_price'] - $currentPrice) / $baseResult['base_price'] * 100;
        $finalProfit = $currentPrice - $baseResult['total_cost'];
        $finalMarginPercent = $baseResult['total_cost'] > 0 
            ? ($finalProfit / $baseResult['total_cost']) * 100 
            : 0;

        return [
            'base_price' => $currentPrice,
            'total_cost' => $baseResult['total_cost'],
            'profit_amount' => $finalProfit,
            'profit_margin_percent' => $finalMarginPercent,
            'currency' => $baseResult['currency'],
            'applied_discounts' => [
                'total_discount_percent' => $actualDiscountPercent,
                'original_price' => $baseResult['base_price'],
                'discounted_price' => $currentPrice,
            ]
        ];
    }

    /**
     * Update product pricing with atomic operations and locking
     */
    public function updateProductPricing(
        Product $product,
        array $options = []
    ): array {
        $lockKey = "product_pricing_{$product->id}";
        
        return Cache::lock($lockKey, self::LOCK_TTL)->block(5, function () use ($product, $options) {
            return DB::transaction(function () use ($product, $options) {
                // Refresh product data within transaction
                $product->refresh();
                
                if ($product->cj_lock_price && !($options['force_update'] ?? false)) {
                    throw new RuntimeException('Product price is locked');
                }

                $calculation = $this->calculateBasePrice(
                    supplierCost: (float) $product->cost_price,
                    supplierCurrency: $product->currency ?? 'USD'
                );

                $result = $this->calculateSellingPrice(
                    calculation: $calculation,
                    marginPercent: $options['margin_percent'] ?? $this->minMarginPercent,
                    categoryId: $product->category_id
                );

                // Apply marketing discounts if provided
                if (isset($options['discounts'])) {
                    $result = $this->applyMarketingDiscounts(
                        baseResult: $result,
                        promotionDiscount: $options['discounts']['promotion'] ?? null,
                        campaignDiscount: $options['discounts']['campaign'] ?? null,
                        flashSaleDiscount: $options['discounts']['flash_sale'] ?? null,
                        couponDiscount: $options['discounts']['coupon'] ?? null
                    );
                }

                // Validate minimum profit
                $this->validateMinimumProfit($result);

                // Update product
                $oldPrice = $product->selling_price;
                $product->update([
                    'selling_price' => $result['base_price'],
                    'currency' => $result['currency'],
                ]);

                // Update variants if requested (default to false to prevent unwanted updates)
                if ($options['update_variants'] ?? false) {
                    $this->updateVariantPricing($product, $options);
                }

                return $result;
            });
        });
    }

    /**
     * Set product margin without updating variants
     */
    public function setProductMargin(Product $product, float $marginPercent, array $discounts = []): array
    {
        return $this->updateProductPricing($product, [
            'margin_percent' => $marginPercent,
            'update_variants' => false,
            'discounts' => $discounts,
        ]);
    }

    /**
     * Set product margin and update variants
     */
    public function setProductMarginWithVariants(Product $product, float $marginPercent, array $discounts = []): array
    {
        return $this->updateProductPricing($product, [
            'margin_percent' => $marginPercent,
            'update_variants' => true,
            'discounts' => $discounts,
        ]);
    }

    /**
     * Bulk update pricing with chunking and error handling
     */
    public function bulkUpdatePricing(array $productIds, array $options = []): array
    {
        $results = [
            'successful' => [],
            'errors' => [],
            'summary' => [
                'total_processed' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'success_rate' => 0,
            ]
        ];
        
        // Process in chunks to avoid memory issues
        collect($productIds)->chunk(100)->each(function ($chunk) use ($options, &$results) {
            foreach ($chunk as $productId) {
                try {
                    $product = Product::findOrFail($productId);
                    $result = $this->updateProductPricing($product, $options);
                    $results['successful'][$productId] = $result;
                    $results['summary']['success_count']++;
                } catch (\Exception $e) {
                    $results['errors'][$productId] = $e->getMessage();
                    $results['summary']['error_count']++;
                }
                $results['summary']['total_processed']++;
            }
        });

        $results['summary']['success_rate'] = $results['summary']['total_processed'] > 0 
            ? ($results['summary']['success_count'] / $results['summary']['total_processed']) * 100 
            : 0;

        return $results;
    }

    private function getCategoryMultiplier(?int $categoryId): float
    {
        if ($categoryId === null) {
            return (float) config('pricing.category_multipliers.default', 1.0);
        }

        return (float) (config("pricing.category_multipliers.{$categoryId}") 
            ?? config('pricing.category_multipliers.default', 1.0));
    }

    private function validateMinimumProfit(array $result): void
    {
        $minMargin = (float) config('pricing.minimum_profit_margin', 15);
        $minProfitAmount = $result['total_cost'] * ($minMargin / 100);

        if ($result['profit_amount'] < $minProfitAmount) {
            throw new InvalidArgumentException(
                "Price {$result['base_price']} would result in profit {$result['profit_amount']} " .
                "which is below minimum required {$minProfitAmount}"
            );
        }
    }

    private function updateVariantPricing(Product $product, array $options): void
    {
        foreach ($product->variants as $variant) {
            if ($variant->cost_price <= 0) {
                continue;
            }

            $calculation = $this->calculateBasePrice(
                supplierCost: (float) $variant->cost_price,
                supplierCurrency: $variant->currency ?? $product->currency ?? 'USD'
            );

            $result = $this->calculateSellingPrice(
                calculation: $calculation,
                marginPercent: $options['margin_percent'] ?? $this->minMarginPercent,
                categoryId: $product->category_id
            );

            $this->validateMinimumProfit($result);

            $variant->update([
                'price' => $result['base_price'],
                'currency' => $result['currency'],
            ]);
        }
    }

    private function roundForCurrency(float $amount): float
    {
        $currency = config('pricing.currency.default', 'XOF');
        $precision = $currency === 'XOF' ? 0 : 2; // XOF has no decimals
        return round($amount, $precision);
    }

    /**
     * Calculate the minimum allowed selling price based on cost and margin (no shipping buffer).
     */
    public function minSellingPrice(float $cost): float
    {
        if ($cost < 0) {
            throw new InvalidArgumentException('Cost price cannot be negative.');
        }

        // Apply currency buffer and fees
        $calculation = $this->calculateBasePrice($cost, 'USD');
        
        return round($calculation['total_cost'] * (1 + $this->minMarginPercent / 100), 2);
    }

    /**
     * Validate a selling price against rules.
     */
    public function validatePrice(float $cost, float $selling): void
    {
        $min = $this->minSellingPrice($cost);

        if ($selling < $min) {
            throw new InvalidArgumentException("Selling price must be at least {$min} based on margin rules.");
        }

        if ($selling < $cost) {
            throw new InvalidArgumentException('Selling price cannot be below cost.');
        }
    }

    /**
     * Validate discount against max discount percent (applied on current price).
     */
    public function validateDiscount(float $price, float $discountAmount): void
    {
        if ($price <= 0) {
            throw new InvalidArgumentException('Price must be positive for discount validation.');
        }

        $discountPercent = ($discountAmount / $price) * 100;

        if ($discountPercent > $this->maxDiscountPercent) {
            throw new InvalidArgumentException("Discount exceeds max allowed {$this->maxDiscountPercent}%.");
        }
    }
}
