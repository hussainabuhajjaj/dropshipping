<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_methods_crud(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/mobile/v1/account/payment-methods', [
            'provider' => 'stripe',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'nickname' => 'Personal',
            'provider_ref' => 'tok_123',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'provider', 'brand', 'last4']]);

        $this->assertDatabaseHas('payment_methods', [
            'customer_id' => $customer->id,
            'provider' => 'stripe',
            'last4' => '4242',
        ]);

        $stored = PaymentMethod::query()
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertSame('tok_123', $stored?->meta['provider_ref'] ?? null);

        $list = $this->getJson('/api/mobile/v1/account/payment-methods');
        $list->assertOk()->assertJsonPath('success', true);

        $methodId = $response->json('data.id');
        $delete = $this->deleteJson('/api/mobile/v1/account/payment-methods/' . $methodId);
        $delete->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('payment_methods', [
            'id' => $methodId,
        ]);
    }

    public function test_payment_method_ownership_enforced(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $method = PaymentMethod::create([
            'customer_id' => $other->id,
            'provider' => 'stripe',
            'brand' => 'visa',
            'last4' => '1111',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->deleteJson('/api/mobile/v1/account/payment-methods/' . $method->id);
        $response->assertForbidden()->assertJsonPath('success', false);
    }
}
