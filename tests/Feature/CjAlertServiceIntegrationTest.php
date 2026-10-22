<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Fulfillment\Clients\CJ\CjAlertService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CjAlertServiceIntegrationTest extends TestCase
{
    public function test_alert_sends_email_when_configured(): void
    {
        Mail::fake();

        Config::set('services.cj.alerts_email', 'ops@example.test');

        CjAlertService::alert('Test alert', ['foo' => 'bar']);

        Mail::assertSent(\App\Mail\CjAlert::class);
    }
}
