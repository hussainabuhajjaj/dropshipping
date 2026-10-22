<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CJWebhookLog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CJWebhookControllerTest extends TestCase
{
    public function test_valid_signature_records_and_processes_webhook(): void
    {
        Config::set('services.cj.webhook_secret', 'secret123');

        $payload = ['messageId' => 'mid-123', 'type' => 'product.sync', 'productId' => 'P1'];
        $body = json_encode($payload);
        $sig = Str::lower(hash_hmac('sha256', $body, 'secret123'));

        $resp = $this->postJson('/webhooks/cj', $payload, ['CJ-SIGN' => $sig]);
        $resp->assertStatus(200);

        $this->assertDatabaseHas('cj_webhook_logs', ['message_id' => 'mid-123', 'processed' => true]);
    }

    public function test_duplicate_message_id_is_ignored(): void
    {
        Config::set('services.cj.webhook_secret', 'secret123');

        $payload = ['messageId' => 'mid-dup', 'type' => 'product.sync', 'productId' => 'P1'];
        $body = json_encode($payload);
        $sig = Str::lower(hash_hmac('sha256', $body, 'secret123'));

        $this->postJson('/webhooks/cj', $payload, ['CJ-SIGN' => $sig])->assertStatus(200);
        // Second time should not create a new record (attempts increment happens on existing record)
        $this->postJson('/webhooks/cj', $payload, ['CJ-SIGN' => $sig])->assertStatus(200);

        $rows = CJWebhookLog::query()->where('message_id', 'mid-dup')->get();
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->processed);
    }

    public function test_missing_signature_aborts_when_secret_configured(): void
    {
        Config::set('services.cj.webhook_secret', 'secret123');

        $payload = ['messageId' => 'mid-4', 'type' => 'product.sync'];
        $this->postJson('/webhooks/cj', $payload)->assertStatus(401);
    }
}
