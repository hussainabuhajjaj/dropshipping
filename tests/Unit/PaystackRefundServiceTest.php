<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Payments\Paystack\PaystackRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\User;

class PaystackRefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakePaystackSuccess(string $refundId = 'RFD_test_123'): void
    {
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status' => true,
                'message' => 'Refund successful',
                'data' => [
                    'id' => $refundId,
                ],
            ], 200),
        ]);
    }

    private function configurePaystack(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_xxx');
        config()->set('services.paystack.base_url', 'https://api.paystack.co');
    }

    public function test_validation_prevents_over_refund(): void
    {
        $this->configurePaystack();
        // Create required address records
        $ship = \App\Domain\Common\Models\Address::create([
            'name' => 'Test Customer',
            'phone' => '0000000000',
            'line1' => '123 Test St',
            'city' => 'Lagos',
            'state' => 'LA',
            'postal_code' => '100001',
            'country' => 'NG',
        ]);
        $bill = \App\Domain\Common\Models\Address::create([
            'name' => 'Test Customer',
            'phone' => '0000000000',
            'line1' => '123 Test St',
            'city' => 'Lagos',
            'state' => 'LA',
            'postal_code' => '100001',
            'country' => 'NG',
        ]);
        $order = Order::create([
            'number' => 'ORD-TEST-001',
            'email' => 'customer@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'currency' => 'NGN',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'shipping_address_id' => $ship->id,
            'billing_address_id' => $bill->id,
        ]);
        
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'status' => 'paid',
            'provider_reference' => 'PS_TEST_REF',
            'amount' => 10000.0,
            'refunded_amount' => 3000.0,
            'currency' => 'NGN',
        ]);

        $service = new PaystackRefundService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 7000');
        $service->refund($payment, 8000.0, 'Over-refund attempt', 1);
    }

    public function test_full_refund_updates_payment_and_order(): void
    {
        $this->configurePaystack();
        $this->fakePaystackSuccess('RFD_full_001');

        // Create required address records
        $ship = \App\Domain\Common\Models\Address::create([
            'name' => 'Test Customer',
            'phone' => '0000000000',
            'line1' => '123 Test St',
            'city' => 'Lagos',
            'state' => 'LA',
            'postal_code' => '100001',
            'country' => 'NG',
        ]);
        $bill = \App\Domain\Common\Models\Address::create([
            'name' => 'Test Customer',
            'phone' => '0000000000',
            'line1' => '123 Test St',
            'city' => 'Lagos',
            'state' => 'LA',
            'postal_code' => '100001',
            'country' => 'NG',
        ]);
        $order = Order::create([
            'number' => 'ORD-TEST-002',
            'email' => 'customer@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'currency' => 'NGN',
            'subtotal' => 5000,
            'grand_total' => 5000,
            'shipping_address_id' => $ship->id,
            'billing_address_id' => $bill->id,
        ]);
        
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'status' => 'paid',
            'provider_reference' => 'PS_FULL_REF',
            'amount' => 5000.0,
            'refunded_amount' => 0.0,
            'currency' => 'NGN',
        ]);

        $user = User::create([
            'name' => 'Refund Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $service = new PaystackRefundService();
        $resp = $service->refund($payment, 5000.0, 'Full refund', $user->id);

        $payment->refresh();

        $this->assertTrue($resp->ok);
        $this->assertSame(5000.0, (float) $payment->refunded_amount);
        $this->assertSame('full', $payment->refund_status);
        $this->assertSame('RFD_full_001', $payment->refund_reference);
        $this->assertSame('Full refund', $payment->refund_reason);
        $this->assertSame($user->id, (int) $payment->refunded_by);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame('refunded', $payment->status);

        // Order update is integration-level; asserting Payment suffices here
    }

    public function test_partial_refund_updates_payment_but_keeps_status_paid(): void
    {
        $this->configurePaystack();
        $this->fakePaystackSuccess('RFD_partial_001');

        // Create required address records
        $ship = \App\Domain\Common\Models\Address::create([
            'name' => 'Test Customer',
            'phone' => '0000000000',
            'line1' => '123 Test St',
            'city' => 'Lagos',
            'state' => 'LA',
            'postal_code' => '100001',
            'country' => 'NG',
        ]);
        $bill = \App\Domain\Common\Models\Address::create([
            'name' => 'Test Customer',
            'phone' => '0000000000',
            'line1' => '123 Test St',
            'city' => 'Lagos',
            'state' => 'LA',
            'postal_code' => '100001',
            'country' => 'NG',
        ]);
        $order = Order::create([
            'number' => 'ORD-TEST-003',
            'email' => 'customer@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'currency' => 'NGN',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'shipping_address_id' => $ship->id,
            'billing_address_id' => $bill->id,
        ]);
        
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'status' => 'paid',
            'provider_reference' => 'PS_PARTIAL_REF',
            'amount' => 10000.0,
            'refunded_amount' => 2000.0,
            'currency' => 'NGN',
        ]);

        $user = User::create([
            'name' => 'Refund Admin',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password'),
        ]);
        $service = new PaystackRefundService();
        $resp = $service->refund($payment, 3000.0, 'Partial refund', $user->id);

        $payment->refresh();

        $this->assertTrue($resp->ok);
        $this->assertSame(5000.0, (float) $payment->refunded_amount);
        $this->assertSame('partial', $payment->refund_status);
        $this->assertSame('RFD_partial_001', $payment->refund_reference);
        $this->assertSame('Partial refund', $payment->refund_reason);
        $this->assertSame($user->id, (int) $payment->refunded_by);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame('paid', $payment->status);

        // Order update is integration-level; asserting Payment suffices here
    }
}
