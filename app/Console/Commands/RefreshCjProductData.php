<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshCjProductData extends Command
{
    protected $signature = 'cj:refresh-product-data
        {--batch-size=25 : Process products in batches}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompts}
        {--pid= : Specific CJ PID to process}
        {--skip-recent=24 : Skip products synced within last N hours}
        {--fix-prices : Fix price corruption during refresh}
        {--fix-inventory : Fix inventory during refresh}
        {--resync-variants : Force variant resync}';

    protected $description = 'Refresh CJ product data using enhanced validation and corruption prevention';

    public function handle(): int
    {
        if (PricingService::usesNewEngine()) {
            $this->warn('pricing.use_new_engine is enabled. This legacy refresh command is blocked to avoid mixing pricing engines.');

            return self::INVALID;
        }

        $this->info('🔄 CJ Product Data Refresh');
        $this->info('==========================');

        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $specificPid = $this->option('pid');
        $skipRecentHours = (int) $this->option('skip-recent');
        $fixPrices = $this->option('fix-prices');
        $fixInventory = $this->option('fix-inventory');
        $resyncVariants = $this->option('resync-variants');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Build query
        $query = Product::whereNotNull('cj_pid');

        if ($specificPid) {
            $query->where('cj_pid', $specificPid);
        }

        if ($skipRecentHours > 0) {
            $query->where(function ($q) use ($skipRecentHours) {
                $q->whereNull('cj_synced_at')
                  ->orWhere('cj_synced_at', '<', now()->subHours($skipRecentHours));
            });
        }

        $total = $query->count();
        $this->info("Found {$total} products to refresh");

        if ($total === 0) {
            $this->info("No products found matching criteria.");
            return self::SUCCESS;
        }

        if (!$force && !$dryRun) {
            if (!$this->confirm("Refresh {$total} products? This will re-import data from CJ API.")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $importService = app(CjProductImportService::class);
        $processed = 0;
        $updated = 0;
        $errors = 0;

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query->chunk($batchSize, function ($products) use ($importService, $dryRun, &$processed, &$updated, &$errors, $progress, $fixPrices, $fixInventory, $resyncVariants) {
            foreach ($products as $product) {
                try {
                    $processed++;
                    
                    if ($dryRun) {
                        $this->line("\n[DRY RUN] Would refresh product {$product->cj_pid}:");
                        $this->line("  Name: " . substr($product->name, 0, 40) . "...");
                        $this->line("  Current price: \${$product->selling_price}");
                        $this->line("  Current stock: {$product->stock_on_hand}");
                        $this->line("  Variants: " . $product->variants()->count());
                    } else {
                        // Re-import product data with enhanced validation
                        $refreshedProduct = $importService->importByPid($product->cj_pid, [
                            'updateExisting' => true,
                            'respectSyncFlag' => false, // Force refresh
                            'respectLocks' => false,   // Force refresh
                            'syncVariants' => $resyncVariants,
                            'syncImages' => false,     // Skip images for speed
                            'generateSeo' => false,     // Skip SEO for speed
                            'translate' => false,      // Skip translation for speed
                            'syncReviews' => false,    // Skip reviews for speed
                        ]);

                        if ($refreshedProduct) {
                            $updated++;
                            
                            // Additional fixes if requested
                            if ($fixPrices) {
                                $this->fixProductPrices($refreshedProduct);
                            }
                            
                            if ($fixInventory) {
                                $this->fixProductInventory($refreshedProduct);
                            }

                            Log::info('Product data refreshed', [
                                'cj_pid' => $product->cj_pid,
                                'product_id' => $refreshedProduct->id,
                                'old_price' => $product->getOriginal('selling_price'),
                                'new_price' => $refreshedProduct->selling_price,
                                'old_stock' => $product->getOriginal('stock_on_hand'),
                                'new_stock' => $refreshedProduct->stock_on_hand,
                            ]);
                        } else {
                            $this->line("\n⚠️  Failed to refresh product {$product->cj_pid}");
                            $errors++;
                        }
                    }

                } catch (\Exception $e) {
                    $this->line("\n❌ Error refreshing product {$product->cj_pid}: " . $e->getMessage());
                    $errors++;
                }

                $progress->advance();
            }
        });

        $progress->finish();

        $this->info("\n\n📊 Refresh Summary:");
        $this->table(['Metric', 'Count'], [
            ['Processed', $processed],
            ['Updated', $updated],
            ['Errors', $errors],
        ]);

        if ($dryRun) {
            $this->info("\nRun without --dry-run to apply these updates.");
        } else {
            $this->info("\n✅ Product data refresh completed!");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fixProductPrices(Product $product): void
    {
        // Fix product price if corrupted
        $cost = (float) ($product->cost_price ?? 0);

        if ($product->selling_price > ($cost * 10)) {
            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
            $minPrice = $pricing->minSellingPrice($cost, $product->currency ?? 'USD');
            $reasonablePrice = $cost * 10;
            $newPrice = min($reasonablePrice, max($minPrice, $cost * 1.5));

            $product->update(['selling_price' => $newPrice]);
            
            Log::warning('Product price fixed during refresh', [
                'cj_pid' => $product->cj_pid,
                'old_price' => $product->getOriginal('selling_price'),
                'new_price' => $newPrice,
            ]);
        }

        // Fix variant prices
        $product->variants()->whereRaw('price > cost_price * 10')->each(function ($variant) {
            $cost = (float) ($variant->cost_price ?? 0);
            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
            $minPrice = $pricing->minSellingPrice($cost, $variant->currency ?? 'USD');
            $reasonablePrice = $cost * 10;
            $newPrice = min($reasonablePrice, max($minPrice, $cost * 1.5));

            $variant->update(['price' => $newPrice]);
            
            Log::warning('Variant price fixed during refresh', [
                'cj_vid' => $variant->cj_vid,
                'old_price' => $variant->getOriginal('price'),
                'new_price' => $newPrice,
            ]);
        });
    }

    private function fixProductInventory(Product $product): void
    {
        // Fix product stock to match variants
        $variantsStock = $product->variants()->pluck('stock_on_hand');
        
        if ($variantsStock->isNotEmpty()) {
            $totalStock = $variantsStock->sum();
            $avgStock = (int) ($variantsStock->avg());

            $product->update([
                'stock_on_hand' => $avgStock,
                'cj_total_stock' => $totalStock,
            ]);
        }
    }
}
