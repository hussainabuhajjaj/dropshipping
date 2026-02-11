<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunCjSyncCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $params;

    public int $tries = 1;
    // Allow long-running CJ syncs to complete when dispatched via queue workers.
    // Set a generous timeout (in seconds) to avoid the job being killed
    // by the worker for long imports.
    public int $timeout = 60 * 60 * 6; // 6 hours

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    public function handle(): void
    {
        try {
            // Ensure options are passed as CLI options (prefixed with --)
            $callArgs = [];
            foreach ($this->params as $k => $v) {
                $key = is_string($k) && str_starts_with($k, '--') ? $k : ('--' . $k);
                $callArgs[$key] = $v;
            }

            Artisan::call('cj:sync-products-v2', $callArgs);
        } catch (\Throwable $e) {
            Log::error('RunCjSyncCommandJob failed', ['error' => $e->getMessage(), 'params' => $this->params]);
            throw $e;
        }
    }
}
