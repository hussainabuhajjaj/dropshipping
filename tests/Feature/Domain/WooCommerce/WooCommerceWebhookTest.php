<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\WooCommerce;

use App\Domain\WooCommerce\Models\WooCommerceWebhookLog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WooCommerceWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('woocommerce', [
            'enabled' => true,
            'base_url' => 'https://test-store.com',
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
            'webhook_secret' => 'webhook_secret_456',
            'timeout' => 30,
            'retry_times' => 1,
        ]);

        Queue::fake();
    }

    public function test_valid_webhook_is_processed(): void
    {
        $payload = ['id' => 1001, 'data' => ['status' => 'completed']];
        $body = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha256', $body, 'webhook_secret_456', true));

        $response = $this->postJson('/api/integrations/woocommerce/webhook', $payload, [
            'X-WC-Webhook-Topic' => 'order.updated',
            'X-WC-Webhook-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'queued']);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $payload = ['id' => 1002];
        $body = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha256', $body, 'wrong_secret', true));

        $response = $this->postJson('/api/integrations/woocommerce/webhook', $payload, [
            'X-WC-Webhook-Topic' => 'order.updated',
            'X-WC-Webhook-Signature' => $signature,
        ]);

        $response->assertStatus(401);
    }

    public function test_missing_signature_returns_400(): void
    {
        $response = $this->postJson('/api/integrations/woocommerce/webhook', ['id' => 1003], [
            'X-WC-Webhook-Topic' => 'product.updated',
        ]);

        $response->assertStatus(400);
    }

    public function test_missing_topic_returns_400(): void
    {
        $payload = ['id' => 1003];
        $body = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha256', $body, 'webhook_secret_456', true));

        $response = $this->postJson('/api/integrations/woocommerce/webhook', $payload, [
            'X-WC-Webhook-Signature' => $signature,
        ]);

        $response->assertStatus(400);
    }
}
