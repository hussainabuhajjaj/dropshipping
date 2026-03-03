<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CjSyncExistingStock extends Command
{
    protected $signature = 'cj:sync-existing-stock 
                            {--page-size=20 : Number of products per page}
                            {--delay=500 : Delay between API calls in milliseconds (rate limiting)}
                            {--max-errors=10 : Stop if errors exceed this threshold}
                            {--resume : Resume from last checkpoint}
                            {--fast : Fast mode - reduced delays and larger batches}
                            {--turbo : Turbo mode - maximum speed with minimal delays}
                            {--skip-recent=24 : Skip products synced within this many hours (0 to disable)}
                            {--force : Force sync all products, ignore recent syncs}
                            {--dry-run : Preview what would be synced without making changes}';

    protected $description = 'Smart sync of real-time stock for existing CJ products with auto-pagination and error recovery';

    private const CHECKPOINT_KEY = 'cj_stock_sync_checkpoint';
    private const STATS_KEY = 'cj_stock_sync_stats';

    public function handle(CJDropshippingClient $client): int
    {
        $fast = $this->option('fast');
        $turbo = $this->option('turbo');
        $resume = $this->option('resume');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $skipRecentHours = $force ? 0 : (int) $this->option('skip-recent');

        // Apply speed optimizations
        if ($turbo) {
            $pageSize = 100;
            $delayMs = 50;
            $maxErrors = 20;
            $mode = '🚀 TURBO MODE';
        } elseif ($fast) {
            $pageSize = 50;
            $delayMs = 200;
            $maxErrors = 15;
            $mode = '⚡ FAST MODE';
        } else {
            $pageSize = (int) $this->option('page-size');
            $delayMs = (int) $this->option('delay');
            $maxErrors = (int) $this->option('max-errors');
            $mode = '🐢 SAFE MODE';
        }

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        if ($force) {
            $this->warn('⚠️  FORCE MODE - Syncing all products regardless of last sync time');
        } elseif ($skipRecentHours > 0) {
            $this->info("⏭️  Skipping products synced within last {$skipRecentHours} hours");
        }

        $this->info("🚀 Smart Stock Sync - {$mode}");
        $this->info("⚙️  Settings: {$pageSize} products/page, {$delayMs}ms delay, max {$maxErrors} errors");

        // Get checkpoint for resume
        $startPage = 1;
        $stats = ['processed' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];

        if ($resume) {
            $checkpoint = Cache::get(self::CHECKPOINT_KEY);
            $savedStats = Cache::get(self::STATS_KEY, $stats);
            if ($checkpoint) {
                $startPage = $checkpoint['page'];
                $stats = $savedStats;
                $this->info("📍 Resuming from page {$startPage}");
                $this->info("📊 Previous stats: {$stats['processed']} processed, {$stats['updated']} updated, {$stats['errors']} errors");
            }
        } else {
            // Clear checkpoint for fresh start
            Cache::forget(self::CHECKPOINT_KEY);
            Cache::forget(self::STATS_KEY);
        }

        // Build query with optional recent sync filter
        $query = Product::whereNotNull('cj_pid')->has('variants');
        
        if ($skipRecentHours > 0) {
            $cutoffTime = now()->subHours($skipRecentHours);
            $query->whereHas('variants', function ($q) use ($cutoffTime) {
                $q->where(function ($q) use ($cutoffTime) {
                    $q->whereNull('cj_stock_synced_at')
                      ->orWhere('cj_stock_synced_at', '<', $cutoffTime);
                });
            });
        }
        
        $totalProducts = $query->count();
        $totalPages = (int) ceil($totalProducts / $pageSize);
        
        $this->info("📦 Found {$totalProducts} CJ products ({$totalPages} pages)");
        $this->newLine();

        $currentPage = $startPage;
        $consecutiveErrors = 0;

        while ($currentPage <= $totalPages) {
            $this->info("📄 Processing page {$currentPage}/{$totalPages}");

            try {
                // Get products for current page with same filter
                $queryClone = Product::whereNotNull('cj_pid')->has('variants');
                
                if ($skipRecentHours > 0) {
                    $cutoffTime = now()->subHours($skipRecentHours);
                    $queryClone->whereHas('variants', function ($q) use ($cutoffTime) {
                        $q->where(function ($q) use ($cutoffTime) {
                            $q->whereNull('cj_stock_synced_at')
                              ->orWhere('cj_stock_synced_at', '<', $cutoffTime);
                        });
                    });
                }
                
                $products = $queryClone
                    ->with('variants')
                    ->skip(($currentPage - 1) * $pageSize)
                    ->take($pageSize)
                    ->get();

                if ($products->isEmpty()) {
                    $this->warn("⚠️  No products found on page {$currentPage}, stopping");
                    break;
                }

                $pageBar = $this->output->createProgressBar($products->count());
                $pageBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
                $pageBar->setMessage('Starting...');
                $pageBar->start();

                foreach ($products as $product) {
                    $pageBar->setMessage("Product #{$product->id}");

                    try {
                        $result = $this->syncProductStock($client, $product, $dryRun, $delayMs);
                        
                        $stats['processed']++;
                        if ($result['updated']) {
                            $stats['updated']++;
                        } else {
                            $stats['skipped']++;
                        }
                        
                        $consecutiveErrors = 0; // Reset on success

                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $consecutiveErrors++;
                        
                        $this->newLine();
                        $this->error("❌ Error on product #{$product->id}: {$e->getMessage()}");
                        
                        Log::error('Stock sync failed for product', [
                            'product_id' => $product->id,
                            'page' => $currentPage,
                            'error' => $e->getMessage(),
                        ]);

                        // Stop if too many consecutive errors
                        if ($consecutiveErrors >= 5) {
                            $this->error("🛑 Too many consecutive errors ({$consecutiveErrors}), stopping");
                            $this->saveCheckpoint($currentPage, $stats);
                            return self::FAILURE;
                        }

                        // Stop if total errors exceed threshold
                        if ($stats['errors'] >= $maxErrors) {
                            $this->error("🛑 Error threshold reached ({$stats['errors']}/{$maxErrors}), stopping");
                            $this->saveCheckpoint($currentPage, $stats);
                            return self::FAILURE;
                        }
                    }

                    $pageBar->advance();
                }

                $pageBar->finish();
                $this->newLine();

                // Save checkpoint after each page
                $this->saveCheckpoint($currentPage + 1, $stats);

                // Show page summary
                $this->info("✓ Page {$currentPage} complete: {$products->count()} products processed");
                $this->newLine();

                $currentPage++;

                // Small delay between pages to avoid overwhelming the API
                if ($currentPage <= $totalPages) {
                    usleep(1000 * 100); // 100ms between pages
                }

            } catch (\Exception $e) {
                $this->error("❌ Critical error on page {$currentPage}: {$e->getMessage()}");
                $this->saveCheckpoint($currentPage, $stats);
                
                Log::error('Critical error during stock sync', [
                    'page' => $currentPage,
                    'error' => $e->getMessage(),
                ]);
                
                return self::FAILURE;
            }
        }

        // Clear checkpoint on successful completion
        Cache::forget(self::CHECKPOINT_KEY);
        Cache::forget(self::STATS_KEY);

        $this->newLine();
        $this->info('🎉 Stock sync completed successfully!');
        $this->displaySummary($stats, $dryRun);

        Log::info('CJ stock sync completed', array_merge($stats, [
            'dry_run' => $dryRun,
            'total_pages' => $totalPages,
        ]));

        return self::SUCCESS;
    }

    private function syncProductStock(CJDropshippingClient $client, Product $product, bool $dryRun, int $delayMs): array
    {
        $totalProductStock = 0;
        $variantsUpdated = 0;

        foreach ($product->variants as $variant) {
            $vid = $variant->cj_vid;
            if (!$vid) {
                continue;
            }

            // Rate limiting delay
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            try {
                $stockResponse = $client->getStockByVid($vid);
                $stockData = $stockResponse->data ?? [];

                $variantTotalStock = 0;

                foreach ($stockData as $warehouseStock) {
                    $stock = $warehouseStock['totalInventoryNum'] ?? 
                             $warehouseStock['storageNum'] ?? 
                             $warehouseStock['cjInventoryNum'] ?? 0;
                    $variantTotalStock += (int) $stock;
                }

                if (!$dryRun) {
                    $variant->update([
                        'cj_stock' => $variantTotalStock,
                        'stock_on_hand' => $variantTotalStock > 0 ? (int) ($variantTotalStock / 2) : 0,
                        'cj_stock_synced_at' => now(),
                    ]);
                }

                $totalProductStock += $variantTotalStock;
                $variantsUpdated++;

            } catch (\Exception $e) {
                Log::warning('Failed to sync variant stock', [
                    'vid' => $vid,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other variants
            }
        }

        if (!$dryRun && $variantsUpdated > 0) {
            $product->update([
                'cj_total_stock' => $totalProductStock,
                'stock_on_hand' => $totalProductStock > 0 ? (int) ($totalProductStock / 2) : 0,
            ]);
        }

        return [
            'updated' => $variantsUpdated > 0,
            'total_stock' => $totalProductStock,
            'variants_updated' => $variantsUpdated,
        ];
    }

    private function saveCheckpoint(int $page, array $stats): void
    {
        Cache::put(self::CHECKPOINT_KEY, ['page' => $page], now()->addDays(7));
        Cache::put(self::STATS_KEY, $stats, now()->addDays(7));
        $this->info("💾 Checkpoint saved at page {$page}");
    }

    private function displaySummary(array $stats, bool $dryRun): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Products Processed', $stats['processed']],
                ['Products Updated', $stats['updated']],
                ['Products Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
                ['Success Rate', $stats['processed'] > 0 ? round(($stats['updated'] / $stats['processed']) * 100, 1) . '%' : 'N/A'],
            ]
        );

        if ($dryRun) {
            $this->warn('🔍 This was a DRY RUN - no changes were made');
            $this->info('Run without --dry-run to apply changes');
        } else {
            $this->info('💡 Tip: Use --resume to continue if interrupted');
        }
    }
}
