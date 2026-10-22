<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CjAlertTriggerTest extends TestCase
{
    public function test_500_response_triggers_alerts_email_and_sentry(): void
    {
        Mail::fake();

        $sentrySpy = new class {
            public bool $called = false;
            public function captureMessage($msg)
            {
                $this->called = true;
            }
        };

        // Bind a simple sentry-like service into the container to observe calls
        $this->app->instance('sentry', $sentrySpy);

        Config::set('services.cj', [
            'app_id' => 'app-id',
            'api_key' => 'test-api-key',
            'api_secret' => 'secret',
            'base_url' => 'https://example.test',
            'timeout' => 5,
            'alerts_email' => 'ops@example.test',
        ]);

        Http::fake([
            'https://example.test/*' => Http::response([
                'result' => false,
                'code' => 500,
                'message' => 'Server error',
                'requestId' => 'RID-500',
            ], 500),
        ]);

        $client = CJDropshippingClient::fromConfig();

        $this->expectException(ApiException::class);

        try {
            $client->listProducts(['pageNum' => 1, 'pageSize' => 1]);
        } catch (ApiException $e) {
            // Assert that the CjAlert mailable was sent
            Mail::assertSent(\App\Mail\CjAlert::class);

            // Assert sentry captured message
            $this->assertTrue($sentrySpy->called, 'Sentry captureMessage should have been called');

            // Ensure requestId was attached to the exception
            $this->assertSame('RID-500', $e->requestId);

            throw $e;
        }
    }
}
