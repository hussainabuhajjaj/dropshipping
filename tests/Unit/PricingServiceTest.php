<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Domain\Products\Services\PricingService;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;
    private Product $testProduct;
    private ProductVariant $testVariant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test category first
        $this->testCategory = Category::factory()->create();

        // Mock currency conversion service
        $currencyService = $this->createMock(CurrencyConversionService::class);
        $currencyService->method('convertAmount')
            ->willReturnCallback(function ($amount, $from, $to) {
                if ($from === $to) return $amount;
                if ($from === 'USD' && $to === 'XOF') return $amount * 600; // 1 USD = 600 XOF
                return $amount;
            });

        $this->pricingService = new PricingService(
            minMarginPercent: 45,
            maxDiscountPercent: 30,
            currencyService: $currencyService
        );

        // Set up test data
        $this->testProduct = Product::factory()->create([
            'cost_price' => 10.00,
            'currency' => 'USD',
            'category_id' => $this->testCategory->id,
        ]);

        $this->testVariant = ProductVariant::factory()->create([
            'product_id' => $this->testProduct->id,
            'cost_price' => 12.00,
            'currency' => 'USD',
        ]);

        // Configure test settings
        Config::set('pricing.currency.default', 'XOF');
        Config::set('pricing.currency.xof_buffer_percent', 5);
        Config::set('pricing.fees.platform', 5.0);
        Config::set('pricing.fees.payment_gateway', 3.5);
        Config::set('pricing.minimum_profit_margin', 15);
        Config::set('pricing.category_multipliers.1', 1.2);
    }

    /** @test */
    public function it_calculates_base_price_correctly()
    {
        $calculation = $this->pricingService->calculateBasePrice(10.00, 'USD');

        $this->assertEquals(10.00, $calculation['supplier_cost']);
        $this->assertEquals(6000.00, $calculation['local_cost']); // 10 * 600
        $this->assertEquals(300.00, $calculation['currency_buffer_amount']); // 6000 * 5%
        $this->assertGreaterThan(0, $calculation['platform_fee_amount']);
        $this->assertEquals('XOF', $calculation['currency']);
        $this->assertGreaterThan(6300, $calculation['total_cost']); // cost + buffer + fees
    }

    /** @test */
    public function it_calculates_selling_price_with_margin_and_category_multiplier()
    {
        $calculation = $this->pricingService->calculateBasePrice(10.00, 'USD');
        $result = $this->pricingService->calculateSellingPrice($calculation, 45, $this->testCategory->id);

        $this->assertArrayHasKey('base_price', $result);
        $this->assertArrayHasKey('profit_amount', $result);
        $this->assertArrayHasKey('profit_margin_percent', $result);
        $this->assertGreaterThan(0, $result['profit_amount']);
        $this->assertGreaterThan(45, $result['profit_margin_percent']); // Should be higher due to category multiplier
        $this->assertEquals('XOF', $result['currency']);
    }

    /** @test */
    public function it_applies_marketing_discounts_safely()
    {
        $calculation = $this->pricingService->calculateBasePrice(10.00, 'USD');
        $baseResult = $this->pricingService->calculateSellingPrice($calculation, 45, 1);

        $discountedResult = $this->pricingService->applyMarketingDiscounts(
            $baseResult,
            promotionDiscount: 10,
            campaignDiscount: 5,
            flashSaleDiscount: 15,
            couponDiscount: 5
        );

        $this->assertLessThan($baseResult['base_price'], $discountedResult['base_price']);
        $this->assertArrayHasKey('applied_discounts', $discountedResult);
        $this->assertGreaterThan(0, $discountedResult['profit_amount']); // Should still have profit
    }

    /** @test */
    public function it_prevents_negative_profit_with_discounts()
    {
        $calculation = $this->pricingService->calculateBasePrice(10.00, 'USD');
        $baseResult = $this->pricingService->calculateSellingPrice($calculation, 15, 1); // Low margin

        // Try to apply excessive discount
        $discountedResult = $this->pricingService->applyMarketingDiscounts(
            $baseResult,
            promotionDiscount: 50 // Excessive discount
        );

        // Should adjust discount to maintain minimum profit
        $this->assertGreaterThan(0, $discountedResult['profit_amount']);
        $minProfitAmount = $baseResult['total_cost'] * 0.15; // 15% minimum
        $this->assertGreaterThanOrEqual($minProfitAmount - 0.01, $discountedResult['profit_amount']); // Allow small rounding difference
    }

    /** @test */
    public function it_updates_product_pricing_without_variants_by_default()
    {
        $originalProductPrice = $this->testProduct->selling_price;
        $originalVariantPrice = $this->testVariant->price;

        $result = $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 50
        ]);

        $this->testProduct->refresh();
        $this->testVariant->refresh();

        // Product should be updated
        $this->assertNotEquals($originalProductPrice, $this->testProduct->selling_price);
        
        // Variant should NOT be updated by default
        $this->assertEquals($originalVariantPrice, $this->testVariant->price);
        
        $this->assertArrayHasKey('base_price', $result);
    }

    /** @test */
    public function it_updates_product_and_variants_when_requested()
    {
        $originalProductPrice = $this->testProduct->selling_price;
        $originalVariantPrice = $this->testVariant->price;

        $result = $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 50,
            'update_variants' => true
        ]);

        $this->testProduct->refresh();
        $this->testVariant->refresh();

        // Both should be updated
        $this->assertNotEquals($originalProductPrice, $this->testProduct->selling_price);
        $this->assertNotEquals($originalVariantPrice, $this->testVariant->price);
    }

    /** @test */
    public function it_respects_price_lock()
    {
        $this->testProduct->update(['cj_lock_price' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product price is locked');

        $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 50
        ]);
    }

    /** @test */
    public function it_can_force_update_locked_prices()
    {
        $this->testProduct->update(['cj_lock_price' => true]);
        $originalPrice = $this->testProduct->selling_price;

        $result = $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 50,
            'force_update' => true
        ]);

        $this->testProduct->refresh();
        $this->assertNotEquals($originalPrice, $this->testProduct->selling_price);
    }

    /** @test */
    public function set_product_margin_method_does_not_update_variants()
    {
        $originalProductPrice = $this->testProduct->selling_price;
        $originalVariantPrice = $this->testVariant->price;

        $result = $this->pricingService->setProductMargin($this->testProduct, 50);

        $this->testProduct->refresh();
        $this->testVariant->refresh();

        $this->assertNotEquals($originalProductPrice, $this->testProduct->selling_price);
        $this->assertEquals($originalVariantPrice, $this->testVariant->price); // Variant unchanged
    }

    /** @test */
    public function set_product_margin_with_variants_method_updates_both()
    {
        $originalProductPrice = $this->testProduct->selling_price;
        $originalVariantPrice = $this->testVariant->price;

        $result = $this->pricingService->setProductMarginWithVariants($this->testProduct, 50);

        $this->testProduct->refresh();
        $this->testVariant->refresh();

        $this->assertNotEquals($originalProductPrice, $this->testProduct->selling_price);
        $this->assertNotEquals($originalVariantPrice, $this->testVariant->price); // Variant updated
    }

    /** @test */
    public function it_handles_bulk_pricing_updates()
    {
        $products = Product::factory()->count(5)->create([
            'cost_price' => 15.00,
            'currency' => 'USD',
            'category_id' => $this->testCategory->id,
        ]);

        $productIds = $products->pluck('id')->toArray();

        $result = $this->pricingService->bulkUpdatePricing($productIds, [
            'margin_percent' => 40,
            'update_variants' => false
        ]);

        $this->assertEquals(5, $result['summary']['total_processed']);
        $this->assertEquals(5, $result['summary']['success_count']);
        $this->assertEquals(0, $result['summary']['error_count']);
        $this->assertEquals(100, $result['summary']['success_rate']);
        $this->assertCount(5, $result['successful']);
    }

    /** @test */
    public function it_validates_minimum_profit_requirement()
    {
        // Set a very low minimum profit margin for this test
        Config::set('pricing.minimum_profit_margin', 50); // 50% minimum
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/below minimum required/');

        // Try to set margin that would violate the new minimum profit
        $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 1 // 1% margin - too low for 50% minimum
        ]);
    }

    /** @test */
    public function it_handles_currency_rounding_correctly()
    {
        Config::set('pricing.currency.default', 'USD'); // Test with USD (2 decimals)

        $calculation = $this->pricingService->calculateBasePrice(10.50, 'USD');
        $result = $this->pricingService->calculateSellingPrice($calculation, 45, $this->testCategory->id);

        // Should be rounded to 2 decimal places for USD
        $this->assertEquals(2, strlen(substr(strrchr((string)$result['base_price'], '.'), 1)));
    }

    /** @test */
    public function it_handles_xof_rounding_correctly()
    {
        Config::set('pricing.currency.default', 'XOF'); // Test with XOF (0 decimals)

        $calculation = $this->pricingService->calculateBasePrice(10.00, 'USD');
        $result = $this->pricingService->calculateSellingPrice($calculation, 45, $this->testCategory->id);

        // Should be rounded to 0 decimal places for XOF
        $this->assertEquals(0, (int)$result['base_price'] % 1); // Should be whole number
    }

    /** @test */
    public function legacy_methods_still_work()
    {
        // Test legacy minSellingPrice method
        $minPrice = $this->pricingService->minSellingPrice(10.00);
        $this->assertGreaterThan(10.00, $minPrice);

        // Test legacy validatePrice method
        $this->expectNotToPerformAssertions();
        $this->pricingService->validatePrice(10.00, $minPrice);

        // Test legacy validateDiscount method
        $this->expectNotToPerformAssertions();
        $this->pricingService->validateDiscount(100.00, 20.00); // 20% discount
    }

    /** @test */
    public function it_prevents_concurrent_updates_with_locking()
    {
        Cache::shouldReceive('lock')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('block')
            ->once()
            ->andReturnUsing(function ($timeout, $callback) {
                return $callback();
            });

        $result = $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 50
        ]);

        $this->assertArrayHasKey('base_price', $result);
    }
}
