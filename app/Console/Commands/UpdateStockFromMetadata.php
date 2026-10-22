<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStockFromMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:update-stock-from-metadata 
                            {--dry-run : Show what would be updated without making changes}
                            {--batch-size=100 : Number of records to process in each batch}
                            {--force : Force update even if stock values exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update product variants stock from CJ metadata';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting stock update from metadata...');
        
        $dryRun = $this->option('dry-run');
        $batchSize = $this->option('batch-size');
        $force = $this->option('force');

        try {
            // Get variants with valid metadata
            $query = DB::table('product_variants')
                ->whereNotNull('metadata')
                ->whereRaw('JSON_VALID(metadata) = 1');

            if (!$force) {
                $query->where(function($q) {
                    $q->whereNull('cj_stock')
                      ->orWhereNull('stock_on_hand')
                      ->orWhere('cj_stock', 0)
                      ->orWhere('stock_on_hand', 0);
                });
            }

            $totalVariants = $query->count();
            
            if ($totalVariants === 0) {
                $this->info('No variants found to update.');
                return 0;
            }

            $this->info("Found {$totalVariants} variants to process");

            $progressBar = $this->output->createProgressBar($totalVariants);
            $progressBar->start();

            $updatedCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            $query->chunk($batchSize, function ($variants) use (&$updatedCount, &$errorCount, &$skippedCount, $progressBar, $dryRun) {
                foreach ($variants as $variant) {
                    try {
                        $metadata = json_decode($variant->metadata, true);
                        
                        if (!$metadata || !isset($metadata['cj_variant'])) {
                            $skippedCount++;
                            $progressBar->advance();
                            continue;
                        }

                        // Extract stock information with proper null handling
                        $cjStock = 0;
                        $extractedStock = 0;
                        
                        // Get CJ stock from metadata
                        if (isset($metadata['cj_variant']['inventoryNum']) && 
                            $metadata['cj_variant']['inventoryNum'] !== null && 
                            is_numeric($metadata['cj_variant']['inventoryNum'])) {
                            $cjStock = (int) $metadata['cj_variant']['inventoryNum'];
                        }
                        
                        // Get extracted stock from metadata
                        if (isset($metadata['extracted_stock']) && 
                            $metadata['extracted_stock'] !== null && 
                            is_numeric($metadata['extracted_stock'])) {
                            $extractedStock = (int) $metadata['extracted_stock'];
                        }
                        
                        // Also check inventory_data as fallback
                        if (isset($metadata['inventory_data'][0]['totalInventory']) && 
                            $metadata['inventory_data'][0]['totalInventory'] !== null && 
                            is_numeric($metadata['inventory_data'][0]['totalInventory'])) {
                            $extractedStock = (int) $metadata['inventory_data'][0]['totalInventory'];
                        }

                        // Check if values actually changed
                        $cjStockChanged = $variant->cj_stock != $cjStock;
                        $stockOnHandChanged = $variant->stock_on_hand != $extractedStock;

                        if (!$cjStockChanged && !$stockOnHandChanged) {
                            $skippedCount++;
                            $progressBar->advance();
                            continue;
                        }

                        if (!$dryRun) {
                            DB::table('product_variants')
                                ->where('id', $variant->id)
                                ->update([
                                    'cj_stock' => (int) $cjStock,
                                    'stock_on_hand' => (int) $extractedStock,
                                    'updated_at' => now()
                                ]);
                        }

                        $updatedCount++;

                        if ($dryRun) {
                            $this->line("\nVariant {$variant->id}: cj_stock {$variant->cj_stock} -> {$cjStock}, stock_on_hand {$variant->stock_on_hand} -> {$extractedStock}");
                        }

                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->line("\nError updating variant {$variant->id}: " . $e->getMessage());
                        Log::error("Stock update error for variant {$variant->id}", [
                            'error' => $e->getMessage(),
                            'variant_id' => $variant->id
                        ]);
                    }

                    $progressBar->advance();
                }
            });

            $progressBar->finish();

            $this->newLine();
            $this->info('=== Update Complete ===');
            $this->info("Total variants processed: {$totalVariants}");
            $this->info("Successfully updated: {$updatedCount}");
            $this->info("Skipped (no changes): {$skippedCount}");
            $this->info("Errors: {$errorCount}");

            if ($dryRun) {
                $this->warn('This was a dry run. Use --force to apply changes.');
            }

            // Show summary statistics
            $this->newLine();
            $this->info('=== Stock Summary ===');
            
            $stockStats = DB::table('product_variants')
                ->selectRaw('
                    COUNT(*) as total_variants,
                    SUM(cj_stock) as total_cj_stock,
                    SUM(stock_on_hand) as total_stock_on_hand,
                    COUNT(CASE WHEN cj_stock > 0 THEN 1 END) as variants_with_cj_stock,
                    COUNT(CASE WHEN stock_on_hand > 0 THEN 1 END) as variants_with_stock
                ')
                ->first();

            $this->info("Total variants: {$stockStats->total_variants}");
            $this->info("Total CJ stock: {$stockStats->total_cj_stock}");
            $this->info("Total stock on hand: {$stockStats->total_stock_on_hand}");
            $this->info("Variants with CJ stock: {$stockStats->variants_with_cj_stock}");
            $this->info("Variants with stock: {$stockStats->variants_with_stock}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            Log::error('Stock update command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
