<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Products\Models\Category;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\CjProductSnapshot;
use App\Models\Product;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CjRepairMissingCategoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_missing_category_from_cj_api_payload(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'cj_pid' => 'PID-REPAIR-1',
            'category_id' => null,
            'cj_last_payload' => null,
            'attributes' => [],
        ]);

        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('getProduct')
            ->once()
            ->with('PID-REPAIR-1')
            ->andReturn(ApiResponse::success([
                'pid' => 'PID-REPAIR-1',
                'categoryId' => 'CAT-REPAIR-1',
                'categoryName' => "Women's Clothing/Underwears/Bras",
            ]));

        Artisan::call('cj:repair-missing-categories', [
            '--limit' => 100,
            '--sleep-ms' => 0,
        ]);

        $product->refresh();
        $this->assertNotNull($product->category_id);
        $this->assertSame('CAT-REPAIR-1', Category::query()->find($product->category_id)?->cj_id);
        $this->assertSame('CAT-REPAIR-1', $product->attributes['cj_category_id'] ?? null);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'cj_pid' => 'PID-REPAIR-2',
            'category_id' => null,
            'cj_last_payload' => null,
            'attributes' => [],
        ]);

        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('getProduct')
            ->once()
            ->with('PID-REPAIR-2')
            ->andReturn(ApiResponse::success([
                'pid' => 'PID-REPAIR-2',
                'categoryId' => 'CAT-REPAIR-2',
                'categoryName' => 'Lingerie',
            ]));

        Artisan::call('cj:repair-missing-categories', [
            '--limit' => 100,
            '--sleep-ms' => 0,
            '--dry-run' => true,
        ]);

        $product->refresh();
        $this->assertNull($product->category_id);
        $this->assertDatabaseMissing('categories', ['cj_id' => 'CAT-REPAIR-2']);
    }

    public function test_it_marks_removed_and_backfills_from_snapshot_when_pid_is_off_shelf(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'cj_pid' => 'PID-REPAIR-3',
            'category_id' => null,
            'cj_last_payload' => null,
            'attributes' => [],
            'is_active' => true,
            'cj_sync_enabled' => true,
            'status' => 'active',
        ]);

        CjProductSnapshot::query()->create([
            'pid' => 'PID-REPAIR-3',
            'category_id' => 'CAT-SNAPSHOT-3',
            'payload' => [
                'categoryName' => "Women's Clothing/Underwears/Bras",
            ],
            'synced_at' => now(),
        ]);

        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('getProduct')
            ->once()
            ->with('PID-REPAIR-3')
            ->andThrow(new ApiException(
                'Product has been removed from shelves, pid:PID-REPAIR-3',
                404,
                'PRODUCT_OFF_SHELF'
            ));

        Artisan::call('cj:repair-missing-categories', [
            '--limit' => 100,
            '--sleep-ms' => 0,
        ]);

        $product->refresh();
        $this->assertNotNull($product->category_id);
        $this->assertSame('CAT-SNAPSHOT-3', Category::query()->find($product->category_id)?->cj_id);
        $this->assertFalse((bool) $product->is_active);
        $this->assertFalse((bool) $product->cj_sync_enabled);
        $this->assertSame('draft', $product->status);
        $this->assertNotNull($product->cj_removed_from_shelves_at);
        $this->assertNotEmpty($product->cj_removed_reason);
    }
}
