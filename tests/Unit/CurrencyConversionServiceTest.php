<?php

namespace Tests\Unit;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_usd_to_xaf(): void
    {
        config()->set('currency.rates.USD_XAF', 600);
        config()->set('currency.decimals.XAF', 0);

        $service = new CurrencyConversionService();

        $this->assertSame(600.0, $service->convertUsdToXaf(1.0));
        $this->assertSame(6150.0, $service->convertUsdToXaf(10.25));
    }

    public function test_converts_product_and_variants_to_xaf(): void
    {
        config()->set('currency.rates.USD_XAF', 600);
        config()->set('currency.decimals.XAF', 0);

        $product = Product::factory()->create([
            'selling_price' => 10.25,
            'cost_price' => 5.55,
            'currency' => 'USD',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 12.99,
            'compare_at_price' => 15.99,
            'cost_price' => 6.5,
            'currency' => 'USD',
        ]);

        $service = new CurrencyConversionService();
        $service->convertProductPricesToXaf($product);

        $product->refresh();
        $variant->refresh();

        $this->assertSame('XAF', $product->currency);
        $this->assertSame(6150.0, (float) $product->selling_price);
        $this->assertSame(3330.0, (float) $product->cost_price);

        $this->assertSame('XAF', $variant->currency);
        $this->assertSame(7794.0, (float) $variant->price);
        $this->assertSame(9594.0, (float) $variant->compare_at_price);
        $this->assertSame(3900.0, (float) $variant->cost_price);
    }
}
