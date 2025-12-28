<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    public function test_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'parent_id']
                ],
                'meta',
                'links'
            ]);
    }

    public function test_can_get_category_tree(): void
    {
        $parent = Category::factory()->create(['parent_id' => null]);
        Category::factory()->count(2)->create(['parent_id' => $parent->id]);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/categories/tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'children']
                ]
            ]);
    }

    public function test_can_get_category_children(): void
    {
        $parent = Category::factory()->create(['parent_id' => null]);
        Category::factory()->count(3)->create(['parent_id' => $parent->id]);

        $response = $this->withBearerToken($this->token)
            ->getJson("/api/v1/categories/{$parent->id}/children");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_view_single_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name
                ]
            ]);
    }

    public function test_can_create_category_with_permission(): void
    {
        $data = [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'parent_id' => null
        ];

        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Electronics',
                    'slug' => 'electronics'
                ]
            ]);

        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->withBearerToken($this->token)
            ->putJson("/api/v1/categories/{$category->id}", [
                'name' => 'New Name'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->withBearerToken($this->token)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(403);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->withBearerToken($this->token)
            ->deleteJson("/api/v1/categories/{$parent->id}");

        $response->assertStatus(403);
    }

    public function test_can_delete_empty_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_prevents_circular_parent_reference(): void
    {
        $parent = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        // Try to make parent a child of child (circular reference)
        $response = $this->withBearerToken($this->token)
            ->putJson("/api/v1/categories/{$parent->id}", [
                'parent_id' => $child->id
            ]);

        $response->assertStatus(400);
    }

    public function test_unauthorized_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }

    public function withBearerToken(string $token): static
    {
        return $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ]);
    }
}
