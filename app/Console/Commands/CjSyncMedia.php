<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Jobs\SyncProductMediaChunkJob;
use Illuminate\Console\Command;

class CjSyncMedia extends Command
{
    protected $signature = 'cj:sync-media
        {--limit=0       : Max products to process (0 = all)}
        {--chunk=20      : Products per job chunk}
        {--product-id=   : Sync a single product by ID or CJ PID}
        {--force         : Ignore cj_sync_enabled flag}
        {--queue=media   : Queue name}';

    protected $description = 'Sync CJ product media (images & videos) via chunked background jobs.';

    public function handle(): int
    {
        $queue     = (string)  $this->option('queue');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $limit     = (int) $this->option('limit');
        $productId = $this->option('product-id');
        $force     = (bool) $this->option('force');

        // Single-product mode
        if ($productId) {
            $product = Product::where('cj_pid', $productId)->orWhere('id', $productId)->first();

            if (! $product || ! $product->cj_pid) {
                $this->error("Product not found or has no CJ PID: {$productId}");
                return self::FAILURE;
            }

            SyncProductMediaChunkJob::dispatch([(int) $product->id])->onQueue($queue);
            $this->info("Queued media sync for product [{$product->id}] on [{$queue}].");
            return self::SUCCESS;
        }

        // Bulk mode
        $query = Product::query()
            ->whereNotNull('cj_pid')
            ->where('cj_pid', '!=', '');

        if (! $force) {
            $query->where('cj_sync_enabled', true);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if (empty($ids)) {
            $this->warn('No products found for media sync.');
            return self::SUCCESS;
        }

        $jobCount = 0;
        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            SyncProductMediaChunkJob::dispatch($chunk)->onQueue($queue);
            $jobCount++;
        }

        $this->info("Dispatched {$jobCount} job(s) for " . count($ids) . " product(s) on [{$queue}].");
        return self::SUCCESS;
    }
}
