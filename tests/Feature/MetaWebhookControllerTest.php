<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessMetaWebhookJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaWebhookControllerTest extends TestCase
{
    public function test_meta_can_verify_webhook_subscription(): void
    {
        Config::set('services.meta.verify_token', 'test-verify-token');

        $this->get('/api/webhooks/meta?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=challenge-123')
            ->assertOk()
            ->assertSee('challenge-123');
    }

    public function test_meta_rejects_invalid_verification_token(): void
    {
        Config::set('services.meta.verify_token', 'test-verify-token');

        $this->get('/api/webhooks/meta?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
            ->assertForbidden();
    }

    public function test_valid_meta_event_is_queued(): void
    {
        Queue::fake();
        Config::set('services.meta.app_secret', 'test-app-secret');

        $payload = ['object' => 'instagram', 'entry' => [['id' => '17841480584591912']]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test-app-secret');

        $this->call('POST', '/api/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body)->assertOk();

        Queue::assertPushed(ProcessMetaWebhookJob::class);
    }

    public function test_meta_rejects_invalid_signature(): void
    {
        Config::set('services.meta.app_secret', 'test-app-secret');

        $this->call('POST', '/api/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], '{"object":"instagram","entry":[]}')->assertUnauthorized();
    }
}
