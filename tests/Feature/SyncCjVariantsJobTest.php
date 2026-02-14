<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\SyncCjVariantsJob;
use App\Models\Product;
use App\Services\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncCjVariantsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_variants_when_response_is_plain_array(): void
    {
        $product = Product::factory()->create([
            'cj_pid' => 'PID-PLAIN-1',
        ]);

        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')
            ->once()
            ->with('PID-PLAIN-1')
            ->andReturn(ApiResponse::success([
                [
                    'vid' => 'VID-1001',
                    'variantSku' => 'SKU-1001',
                    'variantName' => 'Color: Red',
                    'variantSellPrice' => 12.34,
                    'stock' => 9,
                ],
            ]));

        $job = new SyncCjVariantsJob('PID-PLAIN-1');
        $job->handle($client);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'cj_vid' => 'VID-1001',
            'sku' => 'SKU-1001',
            'title' => 'Color: Red',
            'price' => '12.34',
            'cj_stock' => 9,
            'stock_on_hand' => 9,
        ]);
    }

    public function test_it_syncs_variants_when_response_uses_list_key(): void
    {
        $product = Product::factory()->create([
            'cj_pid' => 'PID-LIST-1',
        ]);

        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')
            ->once()
            ->with('PID-LIST-1')
            ->andReturn(ApiResponse::success([
                'pageNum' => 1,
                'pageSize' => 20,
                'list' => [
                    [
                        'vid' => 'VID-2001',
                        'variantSku' => 'SKU-2001',
                        'variantName' => 'Size: XL',
                        'variantPrice' => 18.9,
                        'stock' => 3,
                    ],
                ],
            ]));

        $job = new SyncCjVariantsJob('PID-LIST-1');
        $job->handle($client);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'cj_vid' => 'VID-2001',
            'sku' => 'SKU-2001',
            'title' => 'Size: XL',
            'price' => '18.90',
            'cj_stock' => 3,
            'stock_on_hand' => 3,
        ]);
    }

    public function test_it_falls_back_to_variant_name_en_when_variant_name_is_null(): void
    {
        $product = Product::factory()->create([
            'cj_pid' => 'PID-FALLBACK-1',
        ]);

        $client = Mockery::mock(CJDropshippingClient::class);
        $client->shouldReceive('getVariantsByPid')
            ->once()
            ->with('PID-FALLBACK-1')
            ->andReturn(ApiResponse::success([
                [
                    'vid' => 'VID-3001',
                    'variantSku' => 'SKU-3001',
                    'variantName' => null,
                    'variantNameEn' => 'Black M',
                    'variantSellPrice' => 4.08,
                    'stock' => 0,
                ],
            ]));

        $job = new SyncCjVariantsJob('PID-FALLBACK-1');
        $job->handle($client);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'cj_vid' => 'VID-3001',
            'sku' => 'SKU-3001',
            'title' => 'Black M',
            'price' => '4.08',
        ]);
    }
}
