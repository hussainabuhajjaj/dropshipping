<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->get('/profile');

        $response->assertRedirect(route('account.index', absolute: false));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test2@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $customer->refresh();

        $this->assertSame('Test User', $customer->name);
        $this->assertSame('test@example.com', $customer->email);
        $this->assertNull($customer->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test3@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $customer->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($customer->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test4@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest('customer');
        $this->assertSoftDeleted($customer);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test5@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $response = $this
            ->actingAs($customer, 'customer')
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($customer->fresh());
    }
}
