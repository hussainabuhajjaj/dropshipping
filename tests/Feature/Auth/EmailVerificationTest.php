<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-verify@example.com',
            'password' => null,
            'address_line1' => '',
        ]);

        $response = $this->actingAs($customer, 'customer')->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-verify2@example.com',
            'password' => null,
            'address_line1' => '',
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1($customer->email)]
        );

        $response = $this->actingAs($customer, 'customer')->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('account.index', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $customer = Customer::create([
            'first_name' => 'Test',
            'email' => 'test-verify3@example.com',
            'password' => null,
            'address_line1' => '',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($customer, 'customer')->get($verificationUrl);

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }
}
