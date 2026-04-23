<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Domain\Products\Models\ProductImage;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use App\Services\Storefront\HomeBuilderService;
use App\Services\Storefront\ProductMetaExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
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

    public function test_legacy_collection_endpoint_uses_category_style_variant_price_sorting(): void
    {
        $category = Category::factory()->create([
            'slug' => 'womens-clothing',
            'is_active' => true,
        ]);

        $productWithCheaperVariant = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 50,
            'name' => 'Variant Discount Dress',
        ]);

        $productWithHigherBasePrice = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'selling_price' => 20,
            'name' => 'Base Price Dress',
        ]);

        $productWithCheaperVariant->variants()->create([
            'title' => 'Default',
            'sku' => 'variant-discount',
            'price' => 10,
            'currency' => 'USD',
        ]);

        $productWithHigherBasePrice->variants()->create([
            'title' => 'Default',
            'sku' => 'base-price',
            'price' => 20,
            'currency' => 'USD',
        ]);

        $response = $this->getJson('/api/mobile/v1/collections/women-collection?sort=price_asc');

        $response->assertOk()
            ->assertJsonPath('data.products.0.id', $productWithCheaperVariant->id)
            ->assertJsonPath('data.products.1.id', $productWithHigherBasePrice->id);
    }

    public function test_hybrid_collection_endpoint_supports_filtered_pagination(): void
    {
        $category = Category::factory()->create([
            'slug' => 'hybrid-collection-category',
            'is_active' => true,
        ]);

        $manualMatch = Product::factory()->create([
            'is_active' => true,
            'name' => 'Manual Blue Item',
            'attributes' => ['color' => 'Blue'],
            'created_at' => now()->subDay(),
        ]);

        $ruleMatch = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => 'Rule Blue Item',
            'attributes' => ['color' => 'Blue'],
            'created_at' => now(),
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => 'Rule Red Item',
            'attributes' => ['color' => 'Red'],
        ]);

        $collection = StorefrontCollection::query()->create([
            'title' => 'Hybrid Picks',
            'slug' => 'hybrid-picks',
            'is_active' => true,
            'selection_mode' => 'hybrid',
            'rules' => [
                'category_ids' => [$category->id],
            ],
        ]);

        $collection->products()->attach($manualMatch->id, ['position' => 1]);

        $response = $this->getJson('/api/mobile/v1/collections/hybrid-picks?attributes[color]=Blue');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.products.0.id', $ruleMatch->id)
            ->assertJsonPath('data.products.1.id', $manualMatch->id);
    }

    public function test_legacy_collection_endpoint_returns_empty_payload_when_rendering_fails(): void
    {
        $category = Category::factory()->create([
            'slug' => 'womens-clothing',
            'name' => 'Women',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'attributes' => ['color' => 'Blue'],
        ]);

        $this->mock(ProductMetaExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extract')
                ->with([])
                ->andReturn([
                    'attributeDefs' => [],
                    'brands' => null,
                ]);

            $mock->shouldReceive('extract')
                ->with(\Mockery::on(fn (array $products): bool => count($products) > 0))
                ->once()
                ->andThrow(new \RuntimeException('Simulated legacy render failure'));
        });

        $response = $this->getJson('/api/mobile/v1/collections/women-collection');

        $response->assertOk()
            ->assertJsonPath('data.collection.slug', 'women-collection')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data.products')
            ->assertJsonPath('data.filters.attributeDefs', [])
            ->assertJsonPath('data.filters.brands', null);
    }

    public function test_legacy_collection_endpoint_returns_empty_payload_when_product_serialization_fails(): void
    {
        $category = Category::factory()->create([
            'slug' => 'womens-clothing',
            'name' => 'Women',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'name' => 'Blue Dress',
            'attributes' => ['color' => 'Blue'],
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => 'products/test-image.jpg',
            'position' => 1,
        ]);

        $this->mock(HomeBuilderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('normalizeImage')
                ->andThrow(new \RuntimeException('Simulated product serialization failure'));
        });

        $response = $this->getJson('/api/mobile/v1/collections/women-collection');

        $response->assertOk()
            ->assertJsonPath('data.collection.slug', 'women-collection')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data.products')
            ->assertJsonPath('data.filters.attributeDefs.0.key', 'color')
            ->assertJsonPath('data.filters.brands', null);
    }
}
