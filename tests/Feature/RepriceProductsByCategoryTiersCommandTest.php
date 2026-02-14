<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RepriceProductsByCategoryTiersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bulk_activates_only_products_passing_quality_and_validation_checks(): void
    {
        $category = Category::factory()->create();

        Config::set('pricing.category_margin_tiers', [
            [
                'category_ids' => [$category->id],
                'margin_percent' => 40,
            ],
        ]);

        // Eligible: should activate after repricing.
        $eligible = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'status' => 'draft',
            'name' => 'Eligible Product',
            'slug' => 'eligible-product',
            'cost_price' => 100,
            'selling_price' => 90,
        ]);
        $eligible->images()->create([
            'url' => 'https://example.test/eligible.jpg',
            'position' => 1,
        ]);

        // Low quality: quality check should skip activation.
        $lowQuality = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'status' => 'draft',
            'name' => 'Low Quality Product',
            'slug' => 'low-quality-product',
            'cost_price' => 100,
            'selling_price' => 90,
        ]);
        $lowQuality->variants()->create([
            'title' => 'Default',
            'sku' => 'LOWQ-1',
            'price' => 0,
            'cost_price' => null,
        ]);

        // High quality but invalid: validation should skip activation.
        $validationFail = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'status' => 'draft',
            'name' => '',
            'slug' => 'validation-fail-product',
            'cost_price' => 100,
            'selling_price' => 90,
        ]);
        $validationFail->images()->create([
            'url' => 'https://example.test/validation.jpg',
            'position' => 1,
        ]);

        $this->artisan('products:reprice-by-category-tiers', [
            '--chunk' => 100,
            '--without-compare-at' => true,
            '--min-quality-score' => 70,
        ])->assertExitCode(0);

        $eligible->refresh();
        $lowQuality->refresh();
        $validationFail->refresh();

        $this->assertTrue((bool) $eligible->is_active);
        $this->assertSame('active', $eligible->status);
        $this->assertSame(140.0, (float) $eligible->selling_price);

        $this->assertFalse((bool) $lowQuality->is_active);
        $this->assertSame('draft', $lowQuality->status);
        $this->assertSame(140.0, (float) $lowQuality->selling_price);

        $this->assertFalse((bool) $validationFail->is_active);
        $this->assertSame('draft', $validationFail->status);
        $this->assertSame(140.0, (float) $validationFail->selling_price);
    }
}
