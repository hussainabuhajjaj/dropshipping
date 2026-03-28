<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\ProductCompareAtService;
use Tests\TestCase;

class ProductCompareAtServiceTest extends TestCase
{
    public function test_generates_compare_at_above_variant_price_and_product_selling_price(): void
    {
        config()->set('pricing.compare_at.min_discount_percent', 5);
        config()->set('pricing.compare_at.default_discount_percent', 18);
        config()->set('pricing.compare_at.max_discount_percent', 30);

        $product = Product::factory()->create([
            'currency' => 'USD',
            'selling_price' => 120.00,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'compare_at_price' => null,
        ]);

        $service = app(ProductCompareAtService::class);
        $service->generate($product, true);

        $variant->refresh();
        $product->refresh();

        $this->assertGreaterThan(100.00, (float) $variant->compare_at_price);
        $this->assertGreaterThan(120.00, (float) $variant->compare_at_price);
        $this->assertSame(100.00, (float) $variant->price);
        $this->assertSame(120.00, (float) $product->selling_price);
        $this->assertSame('smart_rules', $variant->metadata['compare_at_strategy']['provider']);
    }

    public function test_reference_price_uses_the_higher_of_variant_and_product_selling_price(): void
    {
        $service = app(ProductCompareAtService::class);

        $this->assertSame(120.0, $service->referencePrice(100.0, 120.0));
        $this->assertSame(120.0, $service->referencePrice(120.0, 100.0));
        $this->assertSame(100.0, $service->referencePrice(100.0, null));
    }

    public function test_is_display_worthy_requires_compare_at_to_clear_minimum_discount(): void
    {
        config()->set('pricing.compare_at.min_discount_percent', 5);

        $service = app(ProductCompareAtService::class);

        $this->assertFalse($service->isDisplayWorthy(100.0, 103.0));
        $this->assertTrue($service->isDisplayWorthy(100.0, 106.0));
        $this->assertFalse($service->isDisplayWorthy(120.0, 120.0));
    }
}
