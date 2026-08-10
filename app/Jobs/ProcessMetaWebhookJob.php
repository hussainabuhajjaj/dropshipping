<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Meta\MetaWebhookParser;
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

    public function handle(MetaWebhookParser $parser): void
    {
        $entries = is_array($this->payload['entry'] ?? null)
            ? $this->payload['entry']
            : [];

        $handled = $parser->process($entries);

        Log::info('Meta webhook received', [
            'object' => $this->payload['object'] ?? null,
            'entry_count' => count($entries),
            'messages_handled' => $handled,
        ]);
    }
}
