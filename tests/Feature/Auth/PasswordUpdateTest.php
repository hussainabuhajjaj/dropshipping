<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-password@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $customer->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-password2@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }
}
