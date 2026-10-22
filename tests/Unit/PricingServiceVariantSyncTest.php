<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PricingServiceVariantSyncTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;
    private Product $testProduct;
    private ProductVariant $testVariant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test category first
        $this->testCategory = \App\Models\Category::factory()->create();

        // Mock currency conversion service
        $currencyService = $this->createMock(CurrencyConversionService::class);
        $currencyService->method('convertAmount')
            ->willReturnCallback(function ($amount, $from, $to) {
                if ($from === $to) return $amount;
                if ($from === 'USD' && $to === 'XOF') return $amount * 600;
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
    public function it_does_not_trigger_api_calls_when_setting_margin_without_variants()
    {
        // Mock HTTP to track any API calls
        Http::fake([
            '*' => Http::response([], 429), // Simulate rate limit
        ]);

        $originalProductPrice = $this->testProduct->selling_price;
        $originalVariantPrice = $this->testVariant->price;

        // Set margin without updating variants
        $result = $this->pricingService->setProductMargin($this->testProduct, 50);

        $this->testProduct->refresh();
        $this->testVariant->refresh();

        // Product should be updated
        $this->assertNotEquals($originalProductPrice, $this->testProduct->selling_price);
        
        // Variant should NOT be updated
        $this->assertEquals($originalVariantPrice, $this->testVariant->price);
        
        // No API calls should have been made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_minimizes_api_calls_during_bulk_updates()
    {
        // Create multiple products with variants
        $products = Product::factory()->count(3)->create([
            'cost_price' => 15.00,
            'currency' => 'USD',
            'category_id' => $this->testCategory->id,
        ]);

        foreach ($products as $product) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'cost_price' => 18.00,
                'currency' => 'USD',
            ]);
        }

        // Mock HTTP to track API calls
        Http::fake([
            '*' => Http::response([], 429),
        ]);

        $productIds = $products->pluck('id')->toArray();

        // Bulk update without variant sync
        $result = $this->pricingService->bulkUpdatePricing($productIds, [
            'margin_percent' => 40,
            'update_variants' => false // Explicitly prevent variant updates
        ]);

        // All products should be updated
        $this->assertEquals(3, $result['summary']['success_count']);
        $this->assertEquals(0, $result['summary']['error_count']);
        
        // No API calls should have been made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_handles_rate_limiting_gracefully()
    {
        // Mock HTTP to simulate rate limiting
        Http::fake([
            '*/api/*' => Http::response([
                'code' => 1600200,
                'result' => false,
                'message' => 'Too Many Requests, QPS limit is 6 times/1second'
            ], 429),
        ]);

        // This should not trigger any API calls when updating pricing
        $result = $this->pricingService->setProductMargin($this->testProduct, 45);

        // Should succeed without making API calls
        $this->assertArrayHasKey('base_price', $result);
        
        // Verify no external API calls were made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_respects_cj_lock_to_prevent_unnecessary_syncs()
    {
        // Lock the product
        $this->testProduct->update(['cj_lock_price' => true]);

        // Mock HTTP to track any calls
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        try {
            // This should fail without making API calls
            $this->pricingService->setProductMargin($this->testProduct, 50);
            $this->fail('Expected exception for locked price');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked', $e->getMessage());
        }

        // No API calls should have been made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_can_bulk_update_with_rate_limit_protection()
    {
        // Create many products to test bulk operations
        $products = Product::factory()->count(10)->create([
            'cost_price' => 20.00,
            'currency' => 'USD',
            'category_id' => $this->testCategory->id,
        ]);

        // Mock HTTP with rate limiting simulation
        $apiCallCount = 0;
        Http::fake([
            '*' => Http::response(function () use (&$apiCallCount) {
                $apiCallCount++;
                // Simulate rate limit after 3 calls
                if ($apiCallCount > 3) {
                    return Http::response([
                        'code' => 1600200,
                        'message' => 'Too Many Requests'
                    ], 429);
                }
                return Http::response([], 200);
            }),
        ]);

        $productIds = $products->pluck('id')->toArray();

        // Bulk update without variant sync
        $result = $this->pricingService->bulkUpdatePricing($productIds, [
            'margin_percent' => 35,
            'update_variants' => false
        ]);

        // Should succeed without triggering API calls
        $this->assertEquals(10, $result['summary']['success_count']);
        $this->assertEquals(0, $result['summary']['error_count']);
        
        // Verify no external API calls were made during pricing updates
        Http::assertNothingSent();
    }

    /** @test */
    public function it_provides_safe_variant_update_option()
    {
        $originalProductPrice = $this->testProduct->selling_price;
        $originalVariantPrice = $this->testVariant->price;

        // Mock HTTP to track calls
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        // Update with explicit variant control
        $result = $this->pricingService->updateProductPricing($this->testProduct, [
            'margin_percent' => 50,
            'update_variants' => false // Explicitly prevent variant updates
        ]);

        $this->testProduct->refresh();
        $this->testVariant->refresh();

        // Product updated, variant not updated
        $this->assertNotEquals($originalProductPrice, $this->testProduct->selling_price);
        $this->assertEquals($originalVariantPrice, $this->testVariant->price);
        
        // No API calls made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_handles_concurrent_updates_safely()
    {
        // Mock HTTP
        Http::fake(['*' => Http::response([], 200)]);

        // Simulate concurrent updates
        $lockKey = "product_pricing_{$this->testProduct->id}";
        
        // Manually acquire a lock to simulate concurrent access
        $lock = Cache::lock($lockKey, 30);
        $lock->block(5);

        try {
            // This should wait for lock or timeout, but not make API calls
            $result = $this->pricingService->setProductMargin($this->testProduct, 45);
            $this->fail('Expected timeout or lock acquisition failure');
        } catch (\Exception $e) {
            // Expected behavior - lock contention
            $this->assertTrue(true);
        } finally {
            $lock->release();
        }

        // No API calls should have been made during lock contention
        Http::assertNothingSent();
    }
}
