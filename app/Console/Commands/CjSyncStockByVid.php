<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\ProductVariant;
use App\Jobs\SyncCjStockByVidChunkJob;
use Illuminate\Console\Command;

class CjSyncStockByVid extends Command
{
    protected $signature = 'cj:sync-stock-by-vid
        {--limit=500 : Number of variants to process}
        {--stale-minutes=30 : Only sync variants whose cj_stock_synced_at is older than this many minutes}
        {--queue=cj-sync : Queue name for jobs}';

    protected $description = 'Sync CJ stock for variants by CJ VID using queryByVid and update local stock_on_hand from totalInventoryNum.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $staleMinutes = (int) $this->option('stale-minutes');
        $queue = (string) $this->option('queue');

        $query = ProductVariant::query()
            ->whereNotNull('cj_vid')
            ->where('cj_vid', '!=', '')
            ->where(function ($q) use ($staleMinutes) {
                $q->whereNull('cj_stock_synced_at')
                    ->orWhere('cj_stock_synced_at', '<', now()->subMinutes(max(1, $staleMinutes)));
            })
            ->orderBy('cj_stock_synced_at', 'asc')
            ->limit(max(1, $limit));

        $vids = $query->pluck('cj_vid')->map(fn ($v) => (string) $v)->filter()->values()->all();

        if ($vids === []) {
            $this->info('No CJ variants require stock sync.');
            return self::SUCCESS;
        }

        $chunkSize = 40;
        $jobCount = 0;
        foreach (array_chunk($vids, $chunkSize) as $chunk) {
            SyncCjStockByVidChunkJob::dispatch($chunk)->onQueue($queue);
            $jobCount++;
        }

        $this->info('Dispatched ' . $jobCount . ' job(s) to sync ' . count($vids) . ' variant(s).');

        return self::SUCCESS;
    }
}
