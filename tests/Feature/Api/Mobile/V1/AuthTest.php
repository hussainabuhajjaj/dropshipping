<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_happy_path(): void
    {
        $payload = [
            'email' => 'mobile@example.com',
            'password' => 'secret123',
            'first_name' => 'Mobile',
            'last_name' => 'User',
            'phone' => '+2250102030405',
            'device_name' => 'ios-test',
        ];

        $response = $this->postJson('/api/mobile/v1/auth/register', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'email'],
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_login_happy_path(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'login@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $customer->email,
            'password' => 'secret123',
            'device_name' => 'android-test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'email'],
                    'token',
                ],
            ]);
    }
}
