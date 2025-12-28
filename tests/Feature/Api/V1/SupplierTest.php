<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_can_list_suppliers(): void
    {
        Supplier::factory()->count(3)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'company', 'rating']
                ],
                'meta',
                'links'
            ]);
    }

    public function test_can_search_suppliers(): void
    {
        Supplier::factory()->create(['name' => 'Acme Corp', 'email' => 'acme@example.com']);
        Supplier::factory()->create(['name' => 'Tech Supply', 'email' => 'tech@example.com']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers?search=acme');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_suppliers_by_country(): void
    {
        Supplier::factory()->create(['country' => 'China']);
        Supplier::factory()->create(['country' => 'Vietnam']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers?country=China');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_suppliers_by_rating(): void
    {
        Supplier::factory()->create(['rating' => 3.5]);
        Supplier::factory()->create(['rating' => 4.5]);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers?min_rating=4');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_suppliers_by_status(): void
    {
        Supplier::factory()->create(['status' => 'active']);
        Supplier::factory()->create(['status' => 'inactive']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers?status=active');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_supplier_statistics(): void
    {
        Supplier::factory()->count(3)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_suppliers',
                    'active_suppliers',
                    'average_rating',
                    'suppliers_by_country'
                ]
            ]);
    }

    public function test_can_get_top_suppliers(): void
    {
        Supplier::factory()->create(['rating' => 4.8, 'status' => 'active']);
        Supplier::factory()->create(['rating' => 4.2, 'status' => 'active']);
        Supplier::factory()->create(['rating' => 3.5, 'status' => 'active']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/suppliers/top?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_view_single_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->getJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name
                ]
            ]);
    }

    public function test_can_create_supplier(): void
    {
        $data = [
            'name' => 'Acme Corp',
            'email' => 'acme@example.com',
            'company' => 'Acme Corporation',
            'country' => 'China',
            'rating' => 4.5,
            'lead_time_days' => 14,
            'minimum_order_qty' => 100,
            'status' => 'active'
        ];

        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/suppliers', $data);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Acme Corp',
                    'email' => 'acme@example.com'
                ]
            ]);

        $this->assertDatabaseHas('suppliers', ['email' => 'acme@example.com']);
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create(['rating' => 3.5]);

        $response = $this->withBearerToken($this->token)
            ->putJson("/api/v1/suppliers/{$supplier->id}", [
                'rating' => 4.5
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'rating' => 4.5]);
    }

    public function test_can_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->deleteJson("/api/v1/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_email_must_be_unique(): void
    {
        Supplier::factory()->create(['email' => 'test@example.com']);

        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/suppliers', [
                'name' => 'Test Supplier',
                'email' => 'test@example.com'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_rating_must_be_between_0_and_5(): void
    {
        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/suppliers', [
                'name' => 'Test Supplier',
                'email' => 'test@example.com',
                'rating' => 6
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_unauthorized_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/suppliers');

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
