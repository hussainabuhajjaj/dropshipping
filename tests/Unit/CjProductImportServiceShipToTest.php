<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\CjProductMediaService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CjProductImportServiceShipToTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): CjProductImportService
    {
        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')->andReturn((object) ['data' => []]);
        $client->shouldReceive('getProduct')->andReturn((object) ['data' => []]);
        $client->shouldReceive('getProductBy')->andReturn((object) ['data' => []]);

        $media = Mockery::mock(CjProductMediaService::class);
        $media->shouldReceive('cleanDescription')->andReturnUsing(fn (?string $desc) => $desc);
        $media->shouldReceive('syncImages')->andReturn(false);
        $media->shouldReceive('syncVideos')->andReturn(false);

        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->instance(CjProductMediaService::class, $media);

        return app(CjProductImportService::class);
    }

    public function test_import_skips_when_ship_to_not_in_warehouses(): void
    {
        $service = $this->makeService();
        $productData = [
            'id' => 'P123',
            'productNameEn' => 'Test Product',
            'productSellPrice' => 10.0,
            'currency' => 'USD',
            'warehouseList' => [
                ['countryCode' => 'US'],
            ],
        ];
        $variants = [];

        $result = $service->importFromPayload($productData, $variants, [
            'shipToCountry' => 'GB',
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => false,
            'syncImages' => false,
        ]);

        $this->assertNull($result, 'Import should skip when ship-to mismatches');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_import_allows_when_warehouses_empty(): void
    {
        $service = $this->makeService();
        $productData = [
            'id' => 'P124',
            'productNameEn' => 'No Warehouse Product',
            'productSellPrice' => 12.5,
            'currency' => 'USD',
        ];
        $variants = [];

        $result = $service->importFromPayload($productData, $variants, [
            'shipToCountry' => 'US',
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => false,
            'syncImages' => false,
        ]);

        $this->assertInstanceOf(Product::class, $result, 'Import should proceed when warehouses cannot be inferred');
        $this->assertDatabaseHas('products', ['cj_pid' => 'P124']);
    }

    public function test_import_allows_when_ship_to_matches(): void
    {
        $service = $this->makeService();
        $productData = [
            'id' => 'P125',
            'productNameEn' => 'Match Product',
            'productSellPrice' => 9.99,
            'currency' => 'USD',
            'warehouseList' => [
                ['countryCode' => 'GB'],
                ['countryCode' => 'US'],
            ],
        ];
        $variants = [];

        $result = $service->importFromPayload($productData, $variants, [
            'shipToCountry' => 'GB',
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncVariants' => false,
            'syncImages' => false,
        ]);

        $this->assertInstanceOf(Product::class, $result, 'Import should proceed when ship-to matches');
        $this->assertDatabaseHas('products', ['cj_pid' => 'P125']);
    }
}
