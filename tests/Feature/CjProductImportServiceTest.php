<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CjProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_returns_null_when_pid_missing(): void
    {
        $client = $this->mock(CJDropshippingClient::class);
        $service = new CjProductImportService($client, app()->make(\App\Domain\Products\Services\CjProductMediaService::class));

        $result = $service->importFromPayload([], null, []);

        $this->assertNull($result);
    }

    public function test_import_creates_product_with_variants_and_images(): void
    {
        DB::table('fulfillment_providers')->insert([
            'id' => 1,
            'name' => 'CJ',
            'code' => 'cj',
            'type' => 'cj',
            'driver_class' => 'App\\Domain\\Fulfillment\\Drivers\\CJDriver',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productData = [
            'pid' => 'CJ-100',
            'productName' => 'CJ T-Shirt',
            'productSellPrice' => '12.50',
            'currency' => 'USD',
            'productImageList' => ['https://example.test/img1.jpg'],
            'description' => '<p>Nice shirt</p><img src="https://example.test/img2.jpg" />',
        ];

        $variants = [
            ['vid' => 'V1', 'variantSku' => 'SKU1', 'variantName' => 'Size M', 'variantSellPrice' => '12.50', 'productImageList' => ['https://example.test/img1.jpg']],
            ['vid' => 'V2', 'variantSku' => 'SKU2', 'variantName' => 'Size L', 'variantSellPrice' => '14.50'],
        ];

        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')->with('CJ-100')->andReturn(ApiResponse::success($variants));
        $client->shouldReceive('getProductReviews')->andReturn(ApiResponse::success([
            'pageNum' => '1',
            'pageSize' => '50',
            'total' => '0',
            'list' => [],
        ]));

        $service = new CjProductImportService($client, app()->make(\App\Domain\Products\Services\CjProductMediaService::class));

        $product = $service->importFromPayload($productData, $variants, []);

        $this->assertNotNull($product);
        $this->assertSame('CJ-100', $product->cj_pid);
        $this->assertSame('CJ T-Shirt', $product->name);
        $this->assertSame(0.0, (float) $product->selling_price);
        $this->assertSame(12.5, (float) $product->cost_price);
        $this->assertSame('USD', $product->currency);

        // Images created
        $this->assertGreaterThanOrEqual(1, $product->images()->count());

        // Variants created
        $this->assertCount(2, $product->variants()->get());
        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU1']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'SKU2']);
    }
}
