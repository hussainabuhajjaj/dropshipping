<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivateProductsByQualityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_activates_only_products_that_pass_quality_and_validation(): void
    {
        $category = Category::factory()->create();

        // Eligible product (quality 90+ and activation validation passes)
        $eligible = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'status' => 'draft',
            'name' => 'Eligible Product',
            'slug' => 'eligible-product',
            'cost_price' => 100,
            'selling_price' => 140,
            'translation_status' => 'completed',
        ]);
        $eligible->images()->create([
            'url' => 'https://example.test/eligible.jpg',
            'position' => 1,
        ]);
        $eligible->translations()->create([
            'locale' => 'en',
            'name' => 'Eligible Product',
            'description' => 'Eligible Description',
        ]);

        // Low quality product (missing image and translation)
        $lowQuality = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'status' => 'draft',
            'name' => 'Low Quality Product',
            'slug' => 'low-quality-product',
            'cost_price' => 100,
            'selling_price' => 140,
            'translation_status' => 'not translated',
        ]);

        // Validation failure product (quality high enough, but missing required name)
        $validationFail = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
            'status' => 'draft',
            'name' => '',
            'slug' => 'validation-fail-product',
            'cost_price' => 100,
            'selling_price' => 140,
            'translation_status' => 'completed',
        ]);
        $validationFail->images()->create([
            'url' => 'https://example.test/validation.jpg',
            'position' => 1,
        ]);
        $validationFail->translations()->create([
            'locale' => 'en',
            'name' => 'Validation Fail Product',
            'description' => 'Validation Description',
        ]);

        $this->artisan('products:activate-by-quality', [
            '--min-quality-score' => 90,
            '--chunk' => 100,
        ])->assertExitCode(0);

        $eligible->refresh();
        $lowQuality->refresh();
        $validationFail->refresh();

        $this->assertTrue((bool) $eligible->is_active);
        $this->assertSame('active', $eligible->status);

        $this->assertFalse((bool) $lowQuality->is_active);
        $this->assertSame('draft', $lowQuality->status);

        $this->assertFalse((bool) $validationFail->is_active);
        $this->assertSame('draft', $validationFail->status);
    }
}

