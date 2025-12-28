<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('Test Token', ['*'])->plainTextToken;
    }

    public function test_can_list_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(5)->create(['category_id' => $category->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'price',
                        'stock',
                    ],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_filter_products_by_search(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create([
            'name' => 'Red Shirt',
            'category_id' => $category->id,
        ]);
        Product::factory()->create([
            'name' => 'Blue Pants',
            'category_id' => $category->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/products?search=Red');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_view_single_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                ],
            ]);
    }

    public function test_can_create_product_with_permission(): void
    {
        $category = Category::factory()->create();
        $token = $this->user->createToken('Admin Token', ['products:create'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'name' => 'New Product',
                'sku' => 'PROD-001',
                'price' => 49.99,
                'stock' => 100,
                'category_id' => $category->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Product',
                    'sku' => 'PROD-001',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-001',
        ]);
    }

    public function test_cannot_create_product_without_permission(): void
    {
        $category = Category::factory()->create();
        $token = $this->user->createToken('Read Only Token', ['products:view'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'name' => 'New Product',
                'sku' => 'PROD-001',
                'price' => 49.99,
                'stock' => 100,
                'category_id' => $category->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_update_product_with_permission(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $token = $this->user->createToken('Admin Token', ['products:update'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'Updated Product Name',
                'price' => 59.99,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Product Name',
                ],
            ]);
    }

    public function test_can_delete_product_with_permission(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $token = $this->user->createToken('Admin Token', ['products:delete'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_product_not_found_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/products/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);
    }

    public function test_cost_price_hidden_without_permission(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'cost_price' => 25.00,
        ]);
        $token = $this->user->createToken('Basic Token', ['products:view'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonMissing(['cost_price']);
    }

    public function test_cost_price_visible_with_permission(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'cost_price' => 25.00,
        ]);
        $token = $this->user->createToken('Admin Token', ['products:view-costs'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.cost_price.amount', 25.00);
    }
}
