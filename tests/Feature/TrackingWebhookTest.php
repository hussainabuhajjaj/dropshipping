<?php

namespace Tests\Feature;

use App\Domain\Common\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_webhook_updates_shipments_and_marks_delivered(): void
    {
        config(['services.tracking.webhook_secret' => 'track-secret']);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::create([
            'number' => 'DS-TESTTRACK',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 49.99,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 49.99,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'placed_at' => now(),
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => null,
            'fulfillment_provider_id' => null,
            'supplier_product_id' => null,
            'fulfillment_status' => 'pending',
            'quantity' => 1,
            'unit_price' => 49.99,
            'total' => 49.99,
            'snapshot' => ['name' => 'Test Product'],
            'meta' => [],
        ]);

        $payload = [
            'order_number' => $order->number,
            'order_item_id' => $orderItem->id,
            'tracking_number' => 'ZX1234567890',
            'carrier' => 'AliExpress',
            'tracking_url' => 'https://tracking.example/ZX1234567890',
            'shipped_at' => '2025-01-10T14:20:00Z',
            'delivered_at' => '2025-01-12T10:00:00Z',
            'events' => [
                [
                    'status_code' => 'delivered',
                    'status_label' => 'Delivered',
                    'description' => 'Delivered to customer',
                    'location' => 'Abidjan',
                    'occurred_at' => '2025-01-12T10:00:00Z',
                ],
            ],
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'track-secret');

        $response = $this->withHeader('X-Signature', $signature)
            ->postJson('/webhooks/tracking/test', $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('shipments', [
            'order_item_id' => $orderItem->id,
            'tracking_number' => 'ZX1234567890',
            'carrier' => 'AliExpress',
        ]);

        $this->assertDatabaseHas('tracking_events', [
            'status_code' => 'delivered',
            'location' => 'Abidjan',
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'fulfillment_status' => 'fulfilled',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'fulfilled',
        ]);
    }
}
