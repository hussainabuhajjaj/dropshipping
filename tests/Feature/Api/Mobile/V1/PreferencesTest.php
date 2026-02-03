<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_lookups_returns_lists(): void
    {
        $response = $this->getJson('/api/mobile/v1/preferences/lookups');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['countries', 'currencies', 'sizes', 'languages'],
            ]);
    }

    public function test_preferences_get_and_update(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $get = $this->getJson('/api/mobile/v1/preferences');
        $get->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['country', 'currency', 'size', 'language', 'notifications'],
            ]);

        $update = $this->patchJson('/api/mobile/v1/preferences', [
            'country' => 'France',
            'currency' => 'EUR (€)',
            'size' => 'EU',
            'language' => 'French',
            'notifications' => [
                'push' => true,
                'email' => false,
                'sms' => true,
            ],
        ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.country', 'France')
            ->assertJsonPath('data.notifications.push', true);
    }
}
