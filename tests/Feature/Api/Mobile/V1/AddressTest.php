<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_addresses_list_and_default_rules(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $existing = Address::factory()->create([
            'customer_id' => $customer->id,
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/mobile/v1/account/addresses', [
            'name' => 'Home',
            'line1' => '123 Test St',
            'city' => 'Abidjan',
            'country' => 'CI',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'line1', 'is_default']]);

        $this->assertDatabaseHas('addresses', [
            'id' => $existing->id,
            'is_default' => false,
        ]);

        $list = $this->getJson('/api/mobile/v1/account/addresses');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'line1', 'is_default']]]);
    }

    public function test_addresses_ownership_enforced(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $address = Address::factory()->create(['customer_id' => $other->id]);

        Sanctum::actingAs($customer);

        $response = $this->patchJson('/api/mobile/v1/account/addresses/' . $address->id, [
            'city' => 'Lagos',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
