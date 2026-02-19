<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PricingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_prevents_rate_limiting_when_updating_pricing()
    {
        // Create test category first
        $category = \App\Models\Category::factory()->create();

        // Create test data
        $product = Product::factory()->create([
            'cost_price' => 15.00,
            'currency' => 'USD',
            'category_id' => $category->id,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'cost_price' => 18.00,
            'currency' => 'USD',
        ]);

        // Mock CJ API with rate limiting
        Http::fake([
            '*/api/cj/*' => Http::response([
                'code' => 1600200,
                'result' => false,
                'message' => 'Too Many Requests, QPS limit is 6 times/1second'
            ], 429),
        ]);

        $pricingService = app(PricingService::class);

        // Capture original prices
        $originalProductPrice = $product->selling_price;
        $originalVariantPrice = $variant->price;

        // Update pricing without variant sync - should NOT trigger CJ API calls
        $result = $pricingService->setProductMargin($product, 45.0);

        // Verify pricing was updated
        $product->refresh();
        $this->assertNotEquals($originalProductPrice, $product->selling_price);

        // Verify variant was NOT updated
        $variant->refresh();
        $this->assertEquals($originalVariantPrice, $variant->price);

        // Most importantly: Verify NO CJ API calls were made
        Http::assertNothingSent();
    }

    /** @test */
    public function it_handles_bulk_pricing_without_rate_limit_issues()
    {
        // Create test category first
        $category = \App\Models\Category::factory()->create();

        // Create multiple products
        $products = Product::factory()->count(5)->create([
            'cost_price' => 20.00,
            'currency' => 'USD',
            'category_id' => $category->id,
        ]);

        // Mock CJ API with strict rate limiting
        Http::fake([
            '*/api/cj/*' => Http::response([
                'code' => 1600200,
                'message' => 'Too Many Requests'
            ], 429),
        ]);

        $pricingService = app(PricingService::class);
        $productIds = $products->pluck('id')->toArray();

        // Bulk update without variant sync
        $result = $pricingService->bulkUpdatePricing($productIds, [
            'margin_percent' => 40.0,
            'update_variants' => false // Key: prevent variant updates
        ]);

        // All products should be updated successfully
        $this->assertEquals(5, $result['summary']['success_count']);
        $this->assertEquals(0, $result['summary']['error_count']);

        // Verify NO CJ API calls were made during pricing updates
        Http::assertNothingSent();
    }
}
