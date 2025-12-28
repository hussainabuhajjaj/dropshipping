<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'phone']
                ],
                'meta',
                'links'
            ]);
    }

    public function test_can_search_customers(): void
    {
        Customer::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Customer::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/customers?search=john');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'John Doe');
    }

    public function test_can_filter_customers_by_city(): void
    {
        Customer::factory()->create(['city' => 'New York']);
        Customer::factory()->create(['city' => 'Boston']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/customers?city=New+York');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_customers_by_country(): void
    {
        Customer::factory()->create(['country' => 'USA']);
        Customer::factory()->create(['country' => 'Canada']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/customers?country=USA');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_customer_statistics(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/customers/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_customers',
                    'total_revenue',
                    'customers_by_country'
                ]
            ]);
    }

    public function test_can_get_top_customers(): void
    {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();

        Order::factory()->count(5)->create(['customer_id' => $customer1->id]);
        Order::factory()->count(2)->create(['customer_id' => $customer2->id]);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/customers/top?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_view_single_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email
                ]
            ]);
    }

    public function test_can_create_customer(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'city' => 'New York',
            'country' => 'USA'
        ];

        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/customers', $data);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ]
            ]);

        $this->assertDatabaseHas('customers', ['email' => 'john@example.com']);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $response = $this->withBearerToken($this->token)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'name' => 'New Name'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_email_must_be_unique(): void
    {
        Customer::factory()->create(['email' => 'test@example.com']);

        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/customers', [
                'name' => 'Test User',
                'email' => 'test@example.com'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unauthorized_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/customers');

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
