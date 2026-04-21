<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_filters_by_child_category_slug(): void
    {
        $root = Category::query()->create([
            'name' => 'Women Clothing',
            'slug' => 'women-clothing',
            'is_active' => true,
        ]);

        $child = Category::query()->create([
            'name' => 'Dresses',
            'slug' => 'dresses',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);

        $other = Category::query()->create([
            'name' => 'Shoes',
            'slug' => 'shoes',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Dress Product',
            'slug' => 'dress-product',
            'is_active' => true,
            'category_id' => $child->id,
            'selling_price' => 30,
        ]);

        Product::factory()->create([
            'name' => 'Shoe Product',
            'slug' => 'shoe-product',
            'is_active' => true,
            'category_id' => $other->id,
            'selling_price' => 40,
        ]);

        $response = $this->get('/products?category=dresses');

        $response->assertStatus(200);
        $response->assertSee('Dress Product');
        $response->assertDontSee('Shoe Product');
    }

    public function test_products_index_filters_by_root_category_and_includes_descendants(): void
    {
        $root = Category::query()->create([
            'name' => 'Women Clothing',
            'slug' => 'women-clothing',
            'is_active' => true,
        ]);

        $child = Category::query()->create([
            'name' => 'Dresses',
            'slug' => 'dresses',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);

        $other = Category::query()->create([
            'name' => 'Shoes',
            'slug' => 'shoes',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Dress Product',
            'slug' => 'dress-product',
            'is_active' => true,
            'category_id' => $child->id,
            'selling_price' => 30,
        ]);

        Product::factory()->create([
            'name' => 'Shoe Product',
            'slug' => 'shoe-product',
            'is_active' => true,
            'category_id' => $other->id,
            'selling_price' => 40,
        ]);

        $response = $this->get('/products?category=women-clothing');

        $response->assertStatus(200);
        $response->assertSee('Dress Product');
        $response->assertDontSee('Shoe Product');
    }
}
