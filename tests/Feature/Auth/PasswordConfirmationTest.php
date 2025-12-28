<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-confirm@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this->actingAs($customer, 'customer')->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-confirm2@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this->actingAs($customer, 'customer')->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-confirm3@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this->actingAs($customer, 'customer')->post('/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    }
}
