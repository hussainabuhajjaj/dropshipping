<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CJSanityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Cheap item import
     * Verify payload correctness for low-value products.
     */
    public function test_cheap_item_import_payload_correctness(): void
    {
        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')->andReturn((object) ['data' => [
            [
                'vid' => 'V123',
                'variantSku' => 'SKU-CHEAP-1',
                'variantName' => 'Color: Red',
                'variantSellPrice' => 5.99,
            ],
        ]]);

        $media = Mockery::mock(\App\Domain\Products\Services\CjProductMediaService::class);
        $media->shouldReceive('cleanDescription')->andReturnUsing(fn ($desc) => $desc);
        $media->shouldReceive('syncImages')->andReturn(true);
        $media->shouldReceive('syncVideos')->andReturn(false);

        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->instance(\App\Domain\Products\Services\CjProductMediaService::class, $media);

        $service = app(CjProductImportService::class);

        $productData = [
            'id' => 'CHEAP001',
            'productNameEn' => 'Cheap Test Item',
            'productSellPrice' => 5.99,
            'currency' => 'USD',
            'descriptionEn' => 'A test item under $10.',
            'categoryId' => 'CAT1',
            'categoryName' => 'Electronics',
        ];

        $product = $service->importFromPayload($productData, null, [
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => true,
            'syncImages' => true,
        ]);

        $this->assertNotNull($product, 'Cheap item should import successfully');
        $this->assertDatabaseHas('products', [
            'cj_pid' => 'CHEAP001',
            'selling_price' => 5.99,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SKU-CHEAP-1',
            'price' => 5.99,
        ]);
    }

    /**
     * Test 2: Variant-heavy item import
     * Verify multiple variants sync correctly.
     */
    public function test_variant_heavy_item_import(): void
    {
        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')->andReturn((object) ['data' => [
            [
                'vid' => 'V201',
                'variantSku' => 'SIZE-S',
                'variantName' => 'Size: Small',
                'variantSellPrice' => 25.00,
            ],
            [
                'vid' => 'V202',
                'variantSku' => 'SIZE-M',
                'variantName' => 'Size: Medium',
                'variantSellPrice' => 26.00,
            ],
            [
                'vid' => 'V203',
                'variantSku' => 'SIZE-L',
                'variantName' => 'Size: Large',
                'variantSellPrice' => 27.00,
            ],
            [
                'vid' => 'V204',
                'variantSku' => 'SIZE-XL',
                'variantName' => 'Size: XL',
                'variantSellPrice' => 28.00,
            ],
        ]]);

        $media = Mockery::mock(\App\Domain\Products\Services\CjProductMediaService::class);
        $media->shouldReceive('cleanDescription')->andReturnUsing(fn ($desc) => $desc);
        $media->shouldReceive('syncImages')->andReturn(false);
        $media->shouldReceive('syncVideos')->andReturn(false);

        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->instance(\App\Domain\Products\Services\CjProductMediaService::class, $media);

        $service = app(CjProductImportService::class);

        $productData = [
            'id' => 'SHIRT001',
            'productNameEn' => 'T-Shirt Multi-Size',
            'productSellPrice' => 25.00,
            'currency' => 'USD',
            'categoryId' => 'CAT-CLOTHING',
            'categoryName' => 'Clothing',
        ];

        $product = $service->importFromPayload($productData, null, [
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => true,
        ]);

        $this->assertNotNull($product, 'Variant-heavy item should import');
        $this->assertEquals(4, $product->variants->count(), 'All 4 variants should sync');
        $this->assertDatabaseHas('product_variants', ['sku' => 'SIZE-S', 'price' => 25.00]);
        $this->assertDatabaseHas('product_variants', ['sku' => 'SIZE-XL', 'price' => 28.00]);
    }

    /**
     * Test 3: Warehouse-filtered item import
     * Verify ship-to filtering works on import.
     */
    public function test_warehouse_filtered_item_import(): void
    {
        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')->andReturn((object) ['data' => []]);

        $media = Mockery::mock(\App\Domain\Products\Services\CjProductMediaService::class);
        $media->shouldReceive('cleanDescription')->andReturnUsing(fn ($desc) => $desc);

        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->instance(\App\Domain\Products\Services\CjProductMediaService::class, $media);

        $service = app(CjProductImportService::class);

        // Product with US warehouse - should import for US, reject for GB
        $productData = [
            'id' => 'GADGET001',
            'productNameEn' => 'US-Only Gadget',
            'productSellPrice' => 49.99,
            'currency' => 'USD',
            'warehouseList' => [
                [
                    'warehouseName' => 'Texas Warehouse',
                    'countryCode' => 'US',
                ],
            ],
        ];

        // Should succeed for US
        $product_us = $service->importFromPayload($productData, [], [
            'shipToCountry' => 'US',
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => false,
            'syncImages' => false,
        ]);

        $this->assertNotNull($product_us, 'Item should import for matching warehouse (US)');

        // Should fail for GB
        $product_gb = $service->importFromPayload($productData, [], [
            'shipToCountry' => 'GB',
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => false,
            'syncImages' => false,
        ]);

        $this->assertNull($product_gb, 'Item should not import when ship-to does not match warehouses');
        $this->assertDatabaseCount('products', 1); // Only US version
    }
}
