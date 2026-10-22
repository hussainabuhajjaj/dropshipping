<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncCjProductsJob;
use Illuminate\Console\Command;

class CjSyncProducts extends Command
{
    protected $signature = 'cj:sync-products {--start-page=1} {--pages=1} {--page-size=24} {--queue}';

    protected $description = 'Sync CJ products into local snapshots';

    public function handle(): int
    {
        $start = (int) $this->option('start-page');
        $pages = (int) $this->option('pages');
        $pageSize = (int) $this->option('page-size');
        $queue = (bool) $this->option('queue');

        $rateSeconds = (int) config('services.cj.rate_limit_seconds', 0);

        for ($i = 0; $i < $pages; $i++) {
            $page = $start + $i;
            $job = new SyncCjProductsJob($page, $pageSize);

            if ($queue) {
                // Delay queued jobs to respect a configured rate (per-page)
                $delay = $i * $rateSeconds;
                dispatch($job)->delay(now()->addSeconds($delay));
                $this->info("Queued CJ sync for page {$page} (delay {$delay}s)");
            } else {
                dispatch_sync($job);
                $this->info("Synced CJ page {$page}");
                if ($rateSeconds > 0 && $i < $pages - 1) {
                    sleep($rateSeconds);
                }
            }
        }

        return self::SUCCESS;
    }
}
