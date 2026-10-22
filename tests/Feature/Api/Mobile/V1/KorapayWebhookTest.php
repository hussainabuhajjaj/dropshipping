<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Domain\Common\Models\Address;
use App\Domain\Payments\Models\PaymentWebhook;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KorapayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_rejected(): void
    {
        config(['services.korapay.webhook_secret' => 'test-secret']);

        $payload = ['event_id' => 'evt_invalid'];

        $response = $this->postJson('/api/webhooks/korapay', $payload);

        // Best practice: respond 200 so Korapay doesn't retry invalid events.
        $response->assertOk()->assertJson(['ignored' => true]);
    }

    public function test_valid_webhook_is_idempotent(): void
    {
        config(['services.korapay.webhook_secret' => 'test-secret']);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::create([
            'number' => 'DS-TESTKORA',
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
            'event_id' => 'evt_kora_123',
            'data' => [
                'id' => 'txn_456',
                'reference' => 'ref_789',
                'amount' => 129.99,
                'currency' => 'USD',
                'status' => 'paid',
                'metadata' => [
                    'order_number' => $order->number,
                ],
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', (string) $dataJson, 'test-secret');

        $response = $this->withHeader('x-korapay-signature', $signature)
            ->postJson('/api/webhooks/korapay', $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'korapay',
            'provider_reference' => 'ref_789',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);

        $this->assertSame(1, PaymentWebhook::query()->count());

        $second = $this->withHeader('x-korapay-signature', $signature)
            ->postJson('/api/webhooks/korapay', $payload);

        $second->assertOk();
        $this->assertSame(1, PaymentWebhook::query()->count());
    }

    public function test_non_charge_events_are_ignored_with_200(): void
    {
        config(['services.korapay.webhook_secret' => 'test-secret']);

        $payload = [
            'event_id' => 'evt_transfer_1',
            'event' => 'transfer.success',
            'data' => [
                'id' => 'txn_1',
                'reference' => 'ref_transfer_1',
                'amount' => 150.99,
                'currency' => 'NGN',
                'status' => 'success',
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', (string) $dataJson, 'test-secret');

        $response = $this->withHeader('x-korapay-signature', $signature)
            ->postJson('/api/webhooks/korapay', $payload);

        $response->assertOk()->assertJson([
            'ignored' => true,
            'event' => 'transfer.success',
        ]);
    }

    public function test_paid_webhook_without_order_number_can_resolve_by_reference(): void
    {
        config(['services.korapay.webhook_secret' => 'test-secret']);

        $address = Address::create([
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $order = Order::create([
            'number' => 'DS-TESTKORA2',
            'email' => 'buyer2@example.com',
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

        // Pre-create payment so we can reconcile by provider_reference even if metadata is missing.
        \App\Models\Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'korapay',
            'status' => 'pending',
            'provider_reference' => 'ref_abc',
            'amount' => 129.99,
            'currency' => 'USD',
            'meta' => [],
        ]);

        $payload = [
            'event_id' => 'evt_kora_999',
            'data' => [
                'id' => 'txn_999',
                'reference' => 'ref_abc',
                'amount' => 129.99,
                'currency' => 'USD',
                'status' => 'success',
                // intentionally no metadata.order_number
            ],
        ];

        $dataJson = json_encode($payload['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', (string) $dataJson, 'test-secret');

        $response = $this->withHeader('x-korapay-signature', $signature)
            ->postJson('/api/webhooks/korapay', $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'korapay',
            'provider_reference' => 'ref_abc',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }
}
