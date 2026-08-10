<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMetaWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly array $payload)
    {
    }

    public function handle(): void
    {
        // Keep ingestion separate from reply generation. Automatic replies require
        // explicit approval and platform-permission checks before being enabled.
        Log::info('Meta webhook received', [
            'object' => $this->payload['object'] ?? null,
            'entry_count' => is_array($this->payload['entry'] ?? null)
                ? count($this->payload['entry'])
                : 0,
        ]);
    }
}
