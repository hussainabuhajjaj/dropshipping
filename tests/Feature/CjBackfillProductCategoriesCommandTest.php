<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Products\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CjBackfillProductCategoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_missing_category_from_cj_payload(): void
    {
        $product = Product::factory()->create([
            'cj_pid' => 'PID-700',
            'category_id' => null,
            'cj_last_payload' => [
                'categoryId' => 'CAT-700',
                'categoryName' => 'Accessories',
            ],
        ]);

        Artisan::call('cj:backfill-product-categories', [
            '--limit' => 100,
        ]);

        $product->refresh();

        $this->assertNotNull($product->category_id);

        $category = Category::query()->where('cj_id', 'CAT-700')->first();
        $this->assertNotNull($category);
        $this->assertSame($category->id, $product->category_id);
    }

    public function test_dry_run_does_not_update_products(): void
    {
        $product = Product::factory()->create([
            'cj_pid' => 'PID-701',
            'category_id' => null,
            'cj_last_payload' => [
                'categoryId' => 'CAT-701',
                'categoryName' => 'Shoes',
            ],
        ]);

        Artisan::call('cj:backfill-product-categories', [
            '--dry-run' => true,
            '--limit' => 100,
        ]);

        $product->refresh();
        $this->assertNull($product->category_id);
        $this->assertDatabaseMissing('categories', ['cj_id' => 'CAT-701']);
    }
}

