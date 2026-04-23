<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_endpoint_supports_sorting_and_price_filters(): void
    {
        $category = Category::factory()->create([
            'slug' => 'jackets',
            'is_active' => true,
        ]);

        $cheap = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 10,
            'name' => 'Cheap Jacket',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 50,
            'name' => 'Expensive Jacket',
        ]);

        $mid = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 20,
            'name' => 'Mid Jacket',
        ]);

        StorefrontCollection::query()->create([
            'title' => 'Jackets',
            'slug' => 'jackets-collection',
            'is_active' => true,
            'selection_mode' => 'rules',
            'rules' => [
                'category_ids' => [$category->id],
            ],
        ]);

        $response = $this->getJson('/api/mobile/v1/collections/jackets-collection?sort=price_asc&max_price=20');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.products.0.id', $cheap->id)
            ->assertJsonPath('data.products.1.id', $mid->id);
    }

    public function test_legacy_collection_endpoint_supports_attribute_filters(): void
    {
        $category = Category::factory()->create([
            'slug' => 'womens-clothing',
            'is_active' => true,
        ]);

        $blue = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => 'Blue Dress',
            'attributes' => ['color' => 'Blue'],
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => 'Red Dress',
            'attributes' => ['color' => 'Red'],
        ]);

        $response = $this->getJson('/api/mobile/v1/collections/women-collection?attributes[color]=Blue');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.products.0.id', $blue->id);
    }
}
