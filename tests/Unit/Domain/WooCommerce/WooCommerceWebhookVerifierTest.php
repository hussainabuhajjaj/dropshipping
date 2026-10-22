<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\WooCommerce;

use App\Domain\WooCommerce\Webhooks\WooCommerceWebhookVerifier;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WooCommerceWebhookVerifierTest extends TestCase
{
    private WooCommerceWebhookVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('woocommerce.webhook_secret', 'test_secret_123');
        $this->verifier = app(WooCommerceWebhookVerifier::class);
    }

    public function test_valid_signature_returns_true(): void
    {
        $payload = '{"id":123,"status":"completed"}';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test_secret_123', true));

        $this->assertTrue($this->verifier->verify($payload, $signature));
    }

    public function test_invalid_signature_returns_false(): void
    {
        $payload = '{"id":123,"status":"completed"}';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'wrong_secret', true));

        $this->assertFalse($this->verifier->verify($payload, $signature));
    }

    public function test_empty_signature_returns_false(): void
    {
        $payload = '{"id":123}';

        $this->assertFalse($this->verifier->verify($payload, ''));
    }

    public function test_no_secret_configured_returns_false(): void
    {
        Config::set('woocommerce.webhook_secret', '');

        $verifier = app(WooCommerceWebhookVerifier::class);
        $this->assertFalse($verifier->verify('{"id":1}', 'any-signature'));
    }
}
