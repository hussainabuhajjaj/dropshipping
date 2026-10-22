<?php

namespace Tests\Feature;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_payment_data_capture_flow()
    {
        echo "\n=== PAYMENT SMOKE TEST START ===\n";

        // 1. Create test order
        $order = Order::factory()->create([
            'number' => 'DS-SMOKE' . time(),
            'grand_total' => 25.50,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'status' => 'pending',
            'email' => 'smoketest@example.com',
            'guest_name' => 'Smoke Test User',
        ]);

        echo "✅ Order Created: {$order->number} (ID: {$order->id})\n";

        // 2. Create payment record
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'provider_reference' => 'krp_smoke_' . time(),
            'status' => 'pending',
            'amount' => 15300.00, // 25.50 * 600 FX rate
            'currency' => 'XOF',
            'meta' => [
                'smoke_test' => true,
                'test_started_at' => now()->toISOString(),
            ],
        ]);

        echo "✅ Payment Created: {$payment->provider_reference} (ID: {$payment->id})\n";

        // 3. Test PaymentService initialization (captures request data)
        $paymentService = app(PaymentService::class);
        
        $initResult = $paymentService->initializeKorapay(
            $order,
            $payment,
            [
                'email' => 'smoketest@example.com',
                'name' => 'Smoke Test User',
                'phone' => '+1234567890',
                'address' => '123 Test Street',
                'city' => 'Test City',
                'country' => 'CI',
            ],
            'mobile_money',
            null // Use default redirect URL
        );

        echo "✅ Payment Initialized\n";
        echo "   - Checkout URL: " . ($initResult['checkout_url'] ?? 'NULL') . "\n";
        echo "   - Reference: " . ($initResult['reference'] ?? 'NULL') . "\n";

        // 4. Verify request data is saved in payment.meta
        $payment->refresh();
        $meta = $payment->meta;

        echo "\n=== REQUEST DATA CAPTURE VERIFICATION ===\n";
        
        $this->assertArrayHasKey('request', $meta, 'Request data should be saved');
        $this->assertArrayHasKey('korapay_init', $meta, 'Korapay init data should be saved');
        $this->assertArrayHasKey('order_amount', $meta, 'Order amount should be saved');
        $this->assertArrayHasKey('charged_amount', $meta, 'Charged amount should be saved');
        $this->assertArrayHasKey('fx_rate_used', $meta, 'FX rate should be saved');

        echo "✅ Request Data: " . (isset($meta['request']) ? 'SAVED' : 'MISSING') . "\n";
        echo "✅ Korapay Init: " . (isset($meta['korapay_init']) ? 'SAVED' : 'MISSING') . "\n";
        echo "✅ Order Amount: " . ($meta['order_amount'] ?? 'MISSING') . "\n";
        echo "✅ Charged Amount: " . ($meta['charged_amount'] ?? 'MISSING') . "\n";
        echo "✅ FX Rate: " . ($meta['fx_rate_used'] ?? 'MISSING') . "\n";

        // 5. Test redirect data capture
        echo "\n=== TESTING REDIRECT DATA CAPTURE ===\n";
        
        $redirectResponse = $this->postJson('/api/mobile/v1/payments/redirect', [
            'reference' => $payment->provider_reference,
            'status' => 'success',
            'transaction_id' => 'txn_smoke_' . time(),
            'amount' => '15300.00',
            'currency' => 'XOF',
            'customer' => [
                'name' => 'Smoke Test User',
                'email' => 'smoketest@example.com',
            ],
            'metadata' => [
                'order_number' => $order->number,
                'payment_id' => $payment->id,
            ],
        ]);

        echo "Redirect Response Status: " . $redirectResponse->status() . "\n";

        // 6. Verify redirect data was saved
        $payment->refresh();
        $redirectMeta = $payment->meta;

        echo "\n=== REDIRECT DATA CAPTURE VERIFICATION ===\n";
        
        $this->assertArrayHasKey('redirect_hit_at', $redirectMeta, 'Redirect hit time should be saved');
        $this->assertArrayHasKey('redirect_payload', $redirectMeta, 'Redirect payload should be saved');

        echo "✅ Redirect Hit At: " . ($redirectMeta['redirect_hit_at'] ?? 'MISSING') . "\n";
        echo "✅ Redirect Payload: " . (isset($redirectMeta['redirect_payload']) ? 'SAVED' : 'MISSING') . "\n";

        if (isset($redirectMeta['redirect_payload'])) {
            echo "   - Query params: " . count($redirectMeta['redirect_payload']['query'] ?? []) . "\n";
            echo "   - Input data: " . count($redirectMeta['redirect_payload']['input'] ?? []) . "\n";
            echo "   - Path: " . ($redirectMeta['redirect_payload']['path'] ?? 'MISSING') . "\n";
        }

        // 7. Test webhook data capture
        echo "\n=== TESTING WEBHOOK DATA CAPTURE ===\n";
        
        $webhookPayload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $payment->provider_reference,
                'status' => 'success',
                'amount' => '15300.00',
                'amount_paid' => '15300.00',
                'currency' => 'XOF',
                'customer' => [
                    'name' => 'Smoke Test User',
                    'email' => 'smoketest@example.com',
                ],
                'metadata' => [
                    'order_number' => $order->number,
                    'payment_id' => $payment->id,
                    'customer_id' => $order->customer_id,
                ],
            ],
        ];

        $webhookResponse = $this->postJson('/webhooks/payment/korapay', $webhookPayload);

        echo "Webhook Response Status: " . $webhookResponse->status() . "\n";

        // 8. Verify webhook data was saved
        $payment->refresh();
        $webhookMeta = $payment->meta;

        echo "\n=== WEBHOOK DATA CAPTURE VERIFICATION ===\n";
        
        // Check PaymentWebhook model
        $webhookRecord = \App\Domain\Payments\Models\PaymentWebhook::where('payment_id', $payment->id)->first();
        
        if ($webhookRecord) {
            echo "✅ Webhook Record: SAVED\n";
            echo "   - Event ID: " . $webhookRecord->external_event_id . "\n";
            echo "   - Provider: " . $webhookRecord->provider . "\n";
            echo "   - Processed: " . ($webhookRecord->processed_at ? 'YES' : 'NO') . "\n";
            echo "   - Payload Size: " . strlen(json_encode($webhookRecord->payload)) . " bytes\n";
        } else {
            echo "❌ Webhook Record: NOT FOUND\n";
        }

        // Check PaymentEvents
        $events = \App\Domain\Payments\Models\PaymentEvent::where('payment_id', $payment->id)->get();
        echo "✅ Payment Events: " . $events->count() . " recorded\n";
        
        foreach ($events as $event) {
            echo "   - {$event->type}: {$event->status} ({$event->created_at})\n";
        }

        // 9. Final verification
        echo "\n=== COMPLETE DATA CAPTURE SUMMARY ===\n";
        
        $finalMeta = $payment->meta;
        $dataPoints = [
            'request' => isset($finalMeta['request']),
            'korapay_init' => isset($finalMeta['korapay_init']),
            'order_amount' => isset($finalMeta['order_amount']),
            'charged_amount' => isset($finalMeta['charged_amount']),
            'fx_rate_used' => isset($finalMeta['fx_rate_used']),
            'redirect_hit_at' => isset($finalMeta['redirect_hit_at']),
            'redirect_payload' => isset($finalMeta['redirect_payload']),
        ];

        foreach ($dataPoints as $key => $exists) {
            echo "✅ {$key}: " . ($exists ? 'CAPTURED' : 'MISSING') . "\n";
        }

        $capturedCount = count(array_filter($dataPoints));
        $totalCount = count($dataPoints);
        $percentage = round(($capturedCount / $totalCount) * 100, 1);

        echo "\n📊 CAPTURE RATE: {$capturedCount}/{$totalCount} ({$percentage}%)\n";

        if ($percentage >= 80) {
            echo "🎉 SMOKE TEST PASSED: Data capture working correctly\n";
        } else {
            echo "⚠️  SMOKE TEST WARNING: Data capture needs improvement\n";
        }

        echo "\n=== PAYMENT SMOKE TEST END ===\n";
    }
}
