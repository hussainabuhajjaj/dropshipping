<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Webhooks;

use Illuminate\Support\Facades\Log;

class WooCommerceWebhookVerifier
{
    public function verify(string $payload, string $signature, ?string $webhookId = null): bool
    {
        $secret = config('woocommerce.webhook_secret', '');

        if ($secret === '') {
            Log::warning('WooCommerce webhook secret not configured, skipping verification');

            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        $isValid = hash_equals($expected, $signature);

        if (! $isValid) {
            Log::warning('WooCommerce webhook signature verification failed', [
                'webhook_id' => $webhookId,
            ]);
        }

        return $isValid;
    }
}
