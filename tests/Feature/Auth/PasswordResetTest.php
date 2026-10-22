<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-reset@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $this->post('/forgot-password', ['email' => $customer->email]);

        Notification::assertSentTo($customer, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-reset2@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $this->post('/forgot-password', ['email' => $customer->email]);

        Notification::assertSentTo($customer, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-reset3@example.com',
            'password' => Hash::make('password'),
            'address_line1' => '',
        ]);

        $this->post('/forgot-password', ['email' => $customer->email]);

        Notification::assertSentTo($customer, ResetPassword::class, function ($notification) use ($customer) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $customer->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
