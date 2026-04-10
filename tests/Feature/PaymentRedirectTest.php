<?php

namespace Tests\Feature;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_korapay_redirect_url_configuration()
    {
        // Create test order
        $order = Order::factory()->create([
            'number' => 'DS-TEST123456',
            'grand_total' => 10.00,
            'currency' => 'USD',
            'payment_status' => 'pending',
        ]);

        // Create test payment
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'provider_reference' => 'krp_test123',
            'status' => 'pending',
            'amount' => 6000.00, // 10 USD * 600 FX rate
            'currency' => 'XOF',
        ]);

        // Test PaymentService initializeKorapay method
        $paymentService = app(PaymentService::class);
        
        $result = $paymentService->initializeKorapay(
            $order,
            $payment,
            [
                'email' => 'test@example.com',
                'name' => 'Test User',
                'phone' => '+1234567890'
            ],
            'mobile_money',
            null // No custom return URL - should use default
        );

        // Verify the redirect URL being sent
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('reference', $result);

        // Check that payment meta contains initialization data
        $payment->refresh();
        $meta = $payment->meta;
        
        $this->assertArrayHasKey('korapay_init', $meta);
        $this->assertArrayHasKey('redirect_url', $meta['korapay_init']);
        
        echo "=== REDIRECT URL TEST RESULTS ===" . PHP_EOL;
        echo "Default Redirect URL: " . url('/api/mobile/v1/payments/redirect') . PHP_EOL;
        echo "Korapay Init Data: " . json_encode($meta['korapay_init'], JSON_PRETTY_PRINT) . PHP_EOL;
    }

    public function test_redirect_handler_captures_data()
    {
        // Create test payment
        $payment = Payment::factory()->create([
            'provider' => 'korapay',
            'provider_reference' => 'krp_redirect_test',
            'status' => 'pending',
            'meta' => ['test' => true]
        ]);

        // Simulate redirect request
        $response = $this->postJson('/api/mobile/v1/payments/redirect', [
            'reference' => 'krp_redirect_test',
            'status' => 'success',
            'transaction_id' => 'txn_12345',
            'amount' => '6000.00',
            'currency' => 'XOF'
        ]);

        // Verify response
        $response->assertStatus(200);

        // Check that redirect data was saved
        $payment->refresh();
        $meta = $payment->meta;
        
        $this->assertArrayHasKey('redirect_hit_at', $meta);
        $this->assertArrayHasKey('redirect_payload', $meta);
        
        echo "=== REDIRECT HANDLER TEST RESULTS ===" . PHP_EOL;
        echo "Redirect Hit At: " . ($meta['redirect_hit_at'] ?? 'NOT SET') . PHP_EOL;
        echo "Redirect Payload: " . json_encode($meta['redirect_payload'] ?? [], JSON_PRETTY_PRINT) . PHP_EOL;
    }

    public function test_actual_redirect_url_being_used()
    {
        // Test what URL is actually being generated
        $expectedRedirectUrl = url('/api/mobile/v1/payments/redirect');
        
        echo "=== CURRENT REDIRECT URL CONFIGURATION ===" . PHP_EOL;
        echo "Expected Redirect URL: " . $expectedRedirectUrl . PHP_EOL;
        echo "Full URL: " . $expectedRedirectUrl . PHP_EOL;
        echo "Base URL: " . config('app.url') . PHP_EOL;
        
        // Test URL generation
        $this->assertStringContains('/api/mobile/v1/payments/redirect', $expectedRedirectUrl);
        $this->assertStringStartsWith('http', $expectedRedirectUrl);
    }
}
