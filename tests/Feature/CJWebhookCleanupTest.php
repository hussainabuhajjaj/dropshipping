<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CJWebhookLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CJWebhookCleanupTest extends TestCase
{
    public function test_cleanup_command_dry_run_finds_duplicates(): void
    {
        CJWebhookLog::create(['message_id' => 'dup-1']);
        CJWebhookLog::create(['message_id' => 'dup-1']);
        CJWebhookLog::create(['message_id' => 'dup-2']);
        CJWebhookLog::create(['message_id' => 'dup-2']);
        CJWebhookLog::create(['message_id' => null]);

        $this->artisan('cj:webhooks-cleanup', ['--dry-run' => true])->assertExitCode(0);

        // duplicates still present after dry run
        $this->assertDatabaseCount('cj_webhook_logs', 5);
    }

    public function test_cleanup_command_removes_duplicates(): void
    {
        CJWebhookLog::create(['message_id' => 'dup-a']);
        CJWebhookLog::create(['message_id' => 'dup-a']);
        CJWebhookLog::create(['message_id' => 'dup-a']);

        $this->artisan('cj:webhooks-cleanup')->assertExitCode(0);

        $this->assertDatabaseCount('cj_webhook_logs', 1);
        $this->assertDatabaseHas('cj_webhook_logs', ['message_id' => 'dup-a']);
    }
}
