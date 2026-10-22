<?php

namespace Tests\Feature;

use App\Domain\Common\Models\Address;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_marks_order_paid_and_creates_payment(): void
    {
        config(['services.payments.webhook_secret' => 'test-secret']);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::create([
            'number' => 'DS-TESTPAY',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 129.99,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 129.99,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $payload = [
            'event_id' => 'evt_123',
            'order_number' => $order->number,
            'amount' => 129.99,
            'currency' => 'USD',
            'status' => 'paid',
            'provider_reference' => 'txn_456',
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->withHeader('X-Signature', $signature)
            ->postJson('/webhooks/payments/test', $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'test',
            'provider_reference' => 'txn_456',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'status' => 'paid',
        ]);
    }
}
