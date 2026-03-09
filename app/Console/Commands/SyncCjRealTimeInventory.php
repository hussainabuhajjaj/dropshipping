<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCjRealTimeInventory extends Command
{
    protected $signature = 'cj:sync-realtime-inventory
        {--batch-size=50 : Process variants in batches}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompts}
        {--pid= : Specific CJ PID to process}
        {--vid= : Specific CJ VID to process}
        {--skip-recent=6 : Skip variants synced within last N hours}';

    protected $description = 'Sync real-time inventory from CJ API for existing products and variants';

    public function handle(): int
    {
        $this->info('🔄 CJ Real-Time Inventory Sync');
        $this->info('==============================');

        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $specificPid = $this->option('pid');
        $specificVid = $this->option('vid');
        $skipRecentHours = (int) $this->option('skip-recent');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Build query
        $query = ProductVariant::whereNotNull('cj_vid')
            ->whereHas('product', function ($q) {
                $q->whereNotNull('cj_pid');
            });

        // Apply filters
        if ($specificPid) {
            $query->whereHas('product', function ($q) use ($specificPid) {
                $q->where('cj_pid', $specificPid);
            });
        }

        if ($specificVid) {
            $query->where('cj_vid', $specificVid);
        }

        if ($skipRecentHours > 0) {
            $query->where(function ($q) use ($skipRecentHours) {
                $q->whereNull('cj_stock_synced_at')
                  ->orWhere('cj_stock_synced_at', '<', now()->subHours($skipRecentHours));
            });
        }

        $total = $query->count();
        $this->info("Found {$total} variants to process");

        if ($total === 0) {
            $this->info("No variants found matching criteria.");
            return self::SUCCESS;
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm("Process {$total} variants? This may take a while.")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $client = new CJDropshippingClient();
        $processed = 0;
        $updated = 0;
        $errors = 0;

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query->chunk($batchSize, function ($variants) use ($client, $dryRun, &$processed, &$updated, &$errors, $progress) {
            foreach ($variants as $variant) {
                try {
                    $processed++;
                    
                    // Get real-time stock from CJ API
                    $response = $client->getStockByVid($variant->cj_vid);
                    
                    if (!$response->ok) {
                        $this->line("\n❌ API Error for VID {$variant->cj_vid}: " . $response->message);
                        $errors++;
                        $progress->advance();
                        continue;
                    }

                    $stockData = $response->data ?? [];
                    $newStock = $this->extractStockFromResponse($stockData);
                    $newStockOnHand = $this->calculateStockOnHand($newStock);

                    $oldStock = $variant->cj_stock ?? 0;
                    $oldStockOnHand = $variant->stock_on_hand ?? 0;

                    if ($oldStock !== $newStock || $oldStockOnHand !== $newStockOnHand) {
                        if ($dryRun) {
                            $this->line("\n[DRY RUN] Would update variant {$variant->cj_vid}:");
                            $this->line("  PID: {$variant->product->cj_pid}");
                            $this->line("  SKU: {$variant->sku}");
                            $this->line("  Stock: {$oldStock} → {$newStock}");
                            $this->line("  Stock on Hand: {$oldStockOnHand} → {$newStockOnHand}");
                        } else {
                            $variant->update([
                                'cj_stock' => $newStock,
                                'stock_on_hand' => $newStockOnHand,
                                'cj_stock_synced_at' => now(),
                            ]);

                            // Update product stock if needed
                            $this->updateProductStock($variant->product);

                            Log::info('Real-time stock synced', [
                                'cj_pid' => $variant->product->cj_pid,
                                'cj_vid' => $variant->cj_vid,
                                'old_stock' => $oldStock,
                                'new_stock' => $newStock,
                                'old_stock_on_hand' => $oldStockOnHand,
                                'new_stock_on_hand' => $newStockOnHand,
                            ]);
                        }
                        $updated++;
                    }

                } catch (\Exception $e) {
                    $this->line("\n❌ Error processing VID {$variant->cj_vid}: " . $e->getMessage());
                    $errors++;
                }

                $progress->advance();
            }
        });

        $progress->finish();

        $this->info("\n\n📊 Sync Summary:");
        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Updated', $updated],
            ['Errors', $errors],
        ]);

        if ($dryRun) {
            $this->info("\nRun without --dry-run to apply these updates.");
        } else {
            $this->info("\n✅ Real-time inventory sync completed!");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function extractStockFromResponse(mixed $stockData): int
    {
        if (!is_array($stockData)) {
            return 0;
        }

        // Handle different response structures
        if (isset($stockData[0]) && is_array($stockData[0])) {
            $data = $stockData[0];
        } else {
            $data = $stockData;
        }

        // Priority order for stock fields
        $stockFields = [
            'totalInventoryNum',
            'totalInventory',
            'inventoryNum',
            'cjInventory',
            'stock',
            'variantStock',
            'quantity'
        ];

        foreach ($stockFields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                return (int) $data[$field];
            }
        }

        return 0;
    }

    private function calculateStockOnHand(int $totalStock): int
    {
        if ($totalStock <= 0) {
            return 0;
        }

        $percentage = (float) config('services.cj.stock_percentage', 75.0);
        $percentage = max(10.0, min(100.0, $percentage));
        
        return (int) ($totalStock * ($percentage / 100.0));
    }

    private function updateProductStock(Product $product): void
    {
        $variantsStock = $product->variants()
            ->whereNotNull('cj_vid')
            ->pluck('stock_on_hand');

        if ($variantsStock->isNotEmpty()) {
            $totalStock = $variantsStock->sum();
            $avgStock = (int) ($variantsStock->avg());

            $product->update([
                'stock_on_hand' => $avgStock,
                'cj_total_stock' => $totalStock,
                'cj_synced_at' => now(),
            ]);
        }
    }
}
