<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSearchSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_returns_empty_payload_for_short_query(): void
    {
        $response = $this->getJson('/search/suggest?q=a');

        $response
            ->assertOk()
            ->assertJson([
                'query' => 'a',
                'products' => [],
                'categories' => [],
            ]);
    }

    public function test_suggest_returns_matching_products_and_categories(): void
    {
        $category = Category::factory()->create([
            'name' => 'Shoes',
            'slug' => 'shoes',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Running Shoes Prime',
            'slug' => 'running-shoes-prime',
            'description' => 'Fast and light running shoes.',
            'is_active' => true,
            'category_id' => $category->id,
            'selling_price' => 89,
        ]);

        Product::factory()->create([
            'name' => 'Office Chair',
            'slug' => 'office-chair',
            'description' => 'Ergonomic desk chair.',
            'is_active' => true,
            'selling_price' => 120,
        ]);

        $response = $this->getJson('/search/suggest?q=shoe');

        $response->assertOk();
        $response->assertJsonPath('query', 'shoe');
        $response->assertJsonCount(1, 'categories');
        $response->assertJsonPath('categories.0.name', 'Shoes');
        $response->assertJsonPath('categories.0.href', '/categories/shoes');

        $productNames = collect($response->json('products'))->pluck('name')->all();
        $this->assertContains('Running Shoes Prime', $productNames);
        $this->assertNotContains('Office Chair', $productNames);
    }
}
