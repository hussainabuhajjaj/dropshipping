<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_access_account(): void
    {
        $customer = Customer::create([
            'first_name' => 'Amina',
            'last_name' => 'Diallo',
            'email' => 'amina@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($customer, 'customer')->get('/account');

        $response->assertStatus(200);
    }

    public function test_admin_user_cannot_access_customer_account(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($user, 'web')->get('/account');

        $response->assertRedirect('/login');
    }
}
