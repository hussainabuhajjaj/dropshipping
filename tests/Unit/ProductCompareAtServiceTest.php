<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AI\DeepSeekClient;
use App\Services\AI\ProductCompareAtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductCompareAtServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_compare_at_for_variant(): void
    {
        $product = Product::factory()->create([
            'currency' => 'USD',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'compare_at_price' => null,
        ]);

        $deep = Mockery::mock(DeepSeekClient::class);
        $deep->shouldReceive('chat')->once()->andReturn(
            '{"variants":[{"id":' . $variant->id . ',"compare_at_price":129.99}]}'
        );

        $service = new ProductCompareAtService($deep);

        $service->generate($product, true);

        $variant->refresh();

        $this->assertSame(129.99, (float) $variant->compare_at_price);
        $this->assertSame('deepseek', $variant->metadata['compare_at_ai']['provider']);
    }

    public function test_regenerates_when_compare_at_below_price_without_force(): void
    {
        $product = Product::factory()->create([
            'currency' => 'USD',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 120.00,
            'compare_at_price' => 110.00,
        ]);

        $deep = Mockery::mock(DeepSeekClient::class);
        $deep->shouldReceive('chat')->once()->andReturn(
            '{"variants":[{"id":' . $variant->id . ',"compare_at_price":149.99}]}'
        );

        $service = new ProductCompareAtService($deep);

        $service->generate($product, false);

        $variant->refresh();

        $this->assertSame(149.99, (float) $variant->compare_at_price);
    }
}
