<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
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

    public function test_can_list_orders(): void
    {
        Order::factory()->count(3)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'order_number', 'customer_id', 'status', 'total']
                ],
                'meta',
                'links'
            ]);
    }

    public function test_can_filter_orders_by_status(): void
    {
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'shipped']);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/orders?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_orders_by_date_range(): void
    {
        Order::factory()->create(['created_at' => now()->subDays(10)]);
        Order::factory()->create(['created_at' => now()->subDays(2)]);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/orders?from_date=' . now()->subDays(5)->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_orders_by_amount_range(): void
    {
        Order::factory()->create(['grand_total' => 50]);
        Order::factory()->create(['grand_total' => 150]);

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/orders?min_amount=100');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_order_statistics(): void
    {
        Order::factory()->count(5)->create();

        $response = $this->withBearerToken($this->token)
            ->getJson('/api/v1/orders/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_orders',
                    'total_revenue',
                    'average_order_value',
                    'orders_by_status'
                ]
            ]);
    }

    public function test_can_view_single_order(): void
    {
        $order = Order::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->number // Use 'number' field
                ]
            ]);
    }

    public function test_can_create_order_with_items(): void
    {
        $customer = Customer::factory()->create();
        $address = \App\Domain\Common\Models\Address::factory()->create(['customer_id' => $customer->id]);
        $product = Product::factory()->create(['selling_price' => 50]);

        $data = [
            'customer_id' => $customer->id,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'subtotal' => 100,
            'tax' => 10,
            'shipping' => 5,
            'total' => 115,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price' => 50
                ]
            ]
        ];

        $response = $this->withBearerToken($this->token)
            ->postJson('/api/v1/orders', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id]);
    }

    public function test_can_update_order_status(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->withBearerToken($this->token)
            ->postJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'shipped'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_can_update_payment_status(): void
    {
        $order = Order::factory()->create(['payment_status' => 'unpaid']);

        $response = $this->withBearerToken($this->token)
            ->postJson("/api/v1/orders/{$order->id}/payment-status", [
                'payment_status' => 'paid'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
    }

    public function test_can_delete_order(): void
    {
        $order = Order::factory()->create();

        $response = $this->withBearerToken($this->token)
            ->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_unauthorized_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/orders');

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
