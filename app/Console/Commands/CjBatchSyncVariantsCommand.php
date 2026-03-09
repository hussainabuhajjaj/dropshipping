<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Jobs\SyncCjVariantsJobImproved;
use App\Services\CjPidClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class CjBatchSyncVariantsCommand extends Command
{
    protected $signature = 'cj:sync-variants-batch 
                            {--limit=100 : Number of products to process}
                            {--queue=cj-sync : Queue to dispatch jobs to}
                            {--priority=low : Job priority (low, normal, high)}
                            {--batch-size=10 : Jobs to dispatch per batch}
                            {--delay=1 : Delay between batches in seconds}';

    protected $description = 'Batch sync CJ product variants with rate limiting and concurrency control';

    public function handle(CjPidClaimService $claimService): int
    {
        $limit = (int) $this->option('limit');
        $queue = $this->option('queue');
        $priority = $this->option('priority');
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');

        $this->info("Starting CJ variants batch sync - Limit: {$limit}, Batch Size: {$batchSize}");

        try {
            $products = $this->getProductsToSync($limit);
            $totalProducts = $products->count();

            if ($totalProducts === 0) {
                $this->info('No products found to sync');
                return Command::SUCCESS;
            }

            $this->info("Found {$totalProducts} products to sync");

            $dispatched = 0;
            $failed = 0;

            // Process in batches to avoid overwhelming the queue
            $products->chunk($batchSize, function ($chunk) use (&$dispatched, &$failed, $queue, $priority, $delay, $claimService) {
                foreach ($chunk as $product) {
                    try {
                        // Try to acquire claim before dispatching job
                        $claimToken = $claimService->claim($product->cj_pid, ttlSeconds: 3600);
                        
                        if (!$claimToken) {
                            $this->warn("Skipping product {$product->cj_pid} - already being processed");
                            $failed++;
                            continue;
                        }

                        // Dispatch job with claim token
                        $job = new SyncCjVariantsJobImproved($product->cj_pid, $claimToken);
                        
                        // Set queue based on priority
                        match ($priority) {
                            'high' => $job->onQueue($queue . '-high'),
                            'low' => $job->onQueue($queue . '-low'),
                            default => $job->onQueue($queue),
                        };

                        dispatch($job);
                        $dispatched++;

                        $this->line("Dispatched sync job for product {$product->cj_pid}");

                    } catch (\Exception $e) {
                        $this->error("Failed to dispatch job for product {$product->cj_pid}: {$e->getMessage()}");
                        $failed++;
                    }
                }

                // Add delay between batches to respect rate limits
                if ($delay > 0) {
                    sleep($delay);
                }
            });

            $this->info("Batch sync completed - Dispatched: {$dispatched}, Failed: {$failed}");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Batch sync failed: {$e->getMessage()}");
            Log::error('CJ batch sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    private function getProductsToSync(int $limit)
    {
        return Product::where('cj_pid', '!=', '')
            ->where('cj_sync_enabled', true)
            ->where(function ($query) {
                $query->whereNull('cj_synced_at')
                    ->orWhere('cj_synced_at', '<', now()->subHours(1))
                    ->orWhere('cj_stock_synced_at', '<', now()->subMinutes(30));
            })
            ->orderBy('cj_synced_at', 'asc')
            ->limit($limit)
            ->select(['id', 'cj_pid', 'cj_synced_at', 'cj_stock_synced_at']);
    }
}
