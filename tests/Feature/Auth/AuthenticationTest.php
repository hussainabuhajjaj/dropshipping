<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_customers_can_authenticate_using_the_login_screen(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-auth@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated('customer');
        $response->assertRedirect(route('account.index', absolute: false));
    }

    public function test_customers_can_not_authenticate_with_invalid_password(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-auth2@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $this->post('/login', [
            'email' => $customer->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('customer');
    }

    public function test_customers_can_logout(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-logout@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this->actingAs($customer, 'customer')->post('/logout');

        $this->assertGuest('customer');
        $response->assertRedirect('/');
    }
}
