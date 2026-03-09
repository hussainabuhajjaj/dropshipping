<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairCjDataCorruption extends Command
{
    protected $signature = 'cj:repair-data-corruption
        {--dry-run : Show what would be fixed without making changes}
        {--batch-size=100 : Process records in batches}
        {--fix-inventory : Fix product-variant inventory inconsistencies}
        {--fix-prices : Fix price corruption issues}
        {--fix-relationships : Fix product-variant relationships}
        {--fix-all : Run all repairs}
        {--force : Skip confirmation prompts}';

    protected $description = 'Repair CJ data corruption issues in products, variants, and inventories';

    public function handle(): int
    {
        $this->info('🔧 CJ Data Corruption Repair Tool');
        $this->info('================================');

        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $fixInventory = $this->option('fix-inventory') || $this->option('fix-all');
        $fixPrices = $this->option('fix-prices') || $this->option('fix-all');
        $fixRelationships = $this->option('fix-relationships') || $this->option('fix-all');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Analyze current state
        $this->analyzeCurrentState();

        if (!$force && !$dryRun) {
            if (!$this->confirm('Do you want to proceed with the repairs?')) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $totalFixed = 0;

        if ($fixInventory) {
            $fixed = $this->repairInventoryInconsistencies($dryRun, $batchSize);
            $totalFixed += $fixed;
        }

        if ($fixPrices) {
            $fixed = $this->repairPriceCorruption($dryRun, $batchSize);
            $totalFixed += $fixed;
        }

        if ($fixRelationships) {
            $fixed = $this->repairProductVariantRelationships($dryRun, $batchSize);
            $totalFixed += $fixed;
        }

        $this->info("\n✅ Repair Summary:");
        $this->info("Total records processed: {$totalFixed}");
        
        if ($dryRun) {
            $this->info("Run without --dry-run to apply these fixes.");
        } else {
            $this->info("All repairs completed successfully!");
        }

        return self::SUCCESS;
    }

    private function analyzeCurrentState(): void
    {
        $this->info("\n📊 Current Data Analysis:");

        $productCount = Product::whereNotNull('cj_pid')->count();
        $variantCount = ProductVariant::whereNotNull('cj_vid')->count();
        
        $inventoryIssues = Product::whereNotNull('cj_pid')
            ->where('stock_on_hand', 0)
            ->whereHas('variants', function ($q) {
                $q->where('stock_on_hand', '>', 0);
            })->count();

        $priceIssues = Product::whereNotNull('cj_pid')
            ->whereRaw('selling_price > cost_price * 10')
            ->count();

        $variantPriceIssues = ProductVariant::whereNotNull('cj_vid')
            ->whereRaw('price > cost_price * 10')
            ->count();

        $orphanedVariants = ProductVariant::whereNotNull('cj_vid')
            ->whereRaw('product_id NOT IN (SELECT id FROM products WHERE cj_pid IS NOT NULL)')
            ->count();

        $this->table(['Metric', 'Count'], [
            ['Products with CJ data', $productCount],
            ['Variants with CJ data', $variantCount],
            ['Inventory inconsistencies', $inventoryIssues],
            ['Products with extreme prices', $priceIssues],
            ['Variants with extreme prices', $variantPriceIssues],
            ['Orphaned variants', $orphanedVariants],
        ]);

        if ($inventoryIssues > 0) {
            $this->error("\n⚠️  Found {$inventoryIssues} products with inventory inconsistencies!");
        }
        if ($priceIssues > 0 || $variantPriceIssues > 0) {
            $this->error("\n⚠️  Found " . ($priceIssues + $variantPriceIssues) . " items with price issues!");
        }
    }

    private function repairInventoryInconsistencies(bool $dryRun, int $batchSize): int
    {
        $this->info("\n🔧 Repairing Inventory Inconsistencies...");

        $fixed = 0;
        $query = Product::whereNotNull('cj_pid')
            ->where('stock_on_hand', 0)
            ->whereHas('variants', function ($q) {
                $q->where('stock_on_hand', '>', 0);
            });

        $total = $query->count();
        $this->info("Found {$total} products with inventory inconsistencies");

        if ($total === 0) {
            $this->info("No inventory issues found.");
            return 0;
        }

        $progress = $this->output->createProgressBar($total);
        $progress->start();

        $query->chunk($batchSize, function ($products) use ($dryRun, &$fixed, $progress) {
            foreach ($products as $product) {
                $variantsStock = $product->variants()
                    ->where('stock_on_hand', '>', 0)
                    ->pluck('stock_on_hand');

                if ($variantsStock->isNotEmpty()) {
                    $totalVariantStock = $variantsStock->sum();
                    $avgVariantStock = (int) ($variantsStock->avg());

                    if ($dryRun) {
                        $this->line("\n[DRY RUN] Would update product {$product->cj_pid}:");
                        $this->line("  Current stock_on_hand: {$product->stock_on_hand}");
                        $this->line("  Would set to: {$avgVariantStock} (avg of " . $variantsStock->count() . " variants)");
                    } else {
                        // Update product stock to match variants
                        $product->update([
                            'stock_on_hand' => $avgVariantStock,
                            'cj_total_stock' => $totalVariantStock,
                            'cj_synced_at' => now(),
                        ]);

                        Log::info('Product inventory repaired', [
                            'cj_pid' => $product->cj_pid,
                            'old_stock' => $product->getOriginal('stock_on_hand'),
                            'new_stock' => $avgVariantStock,
                            'variant_count' => $variantsStock->count()
                        ]);
                    }
                    $fixed++;
                }
                $progress->advance();
            }
        });

        $progress->finish();

        if ($dryRun) {
            $this->info("\n[DRY RUN] Would fix {$fixed} inventory inconsistencies");
        } else {
            $this->info("\n✅ Fixed {$fixed} inventory inconsistencies");
        }

        return $fixed;
    }

    private function repairPriceCorruption(bool $dryRun, int $batchSize): int
    {
        $this->info("\n💰 Repairing Price Corruption...");

        $fixed = 0;

        // Check products with extreme prices
        $productQuery = Product::whereNotNull('cj_pid')
            ->whereRaw('selling_price > cost_price * 10');

        $productCount = $productQuery->count();
        $this->info("Found {$productCount} products with extreme prices");

        if ($productCount > 0) {
            $progress = $this->output->createProgressBar($productCount);
            $progress->start();

            $productQuery->chunk($batchSize, function ($products) use ($dryRun, &$fixed, $progress) {
                foreach ($products as $product) {
                    $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                    $minPrice = $pricing->minSellingPrice($product->cost_price, $product->currency ?? 'USD');
                    $reasonablePrice = $product->cost_price * 10;

                    $newPrice = min($reasonablePrice, max($minPrice, $product->cost_price * 1.5));

                    if ($dryRun) {
                        $this->line("\n[DRY RUN] Would fix product {$product->cj_pid} price:");
                        $this->line("  Current: \${$product->selling_price} (cost: \${$product->cost_price})");
                        $this->line("  Would set to: \${$newPrice}");
                    } else {
                        $product->update([
                            'selling_price' => $newPrice,
                            'cj_synced_at' => now(),
                        ]);

                        Log::warning('Product price corruption repaired', [
                            'cj_pid' => $product->cj_pid,
                            'old_price' => $product->getOriginal('selling_price'),
                            'new_price' => $newPrice,
                            'cost_price' => $product->cost_price
                        ]);
                    }
                    $fixed++;
                    $progress->advance();
                }
            });

            $progress->finish();
        }

        // Check variants with extreme prices
        $variantQuery = ProductVariant::whereNotNull('cj_vid')
            ->whereRaw('price > cost_price * 10');

        $variantCount = $variantQuery->count();
        $this->info("\nFound {$variantCount} variants with extreme prices");

        if ($variantCount > 0) {
            $progress = $this->output->createProgressBar($variantCount);
            $progress->start();

            $variantQuery->chunk($batchSize, function ($variants) use ($dryRun, &$fixed, $progress) {
                foreach ($variants as $variant) {
                    $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                    $minPrice = $pricing->minSellingPrice($variant->cost_price, $variant->currency ?? 'USD');
                    $reasonablePrice = $variant->cost_price * 10;

                    $newPrice = min($reasonablePrice, max($minPrice, $variant->cost_price * 1.5));

                    if ($dryRun) {
                        $this->line("\n[DRY RUN] Would fix variant {$variant->cj_vid} price:");
                        $this->line("  Current: \${$variant->price} (cost: \${$variant->cost_price})");
                        $this->line("  Would set to: \${$newPrice}");
                    } else {
                        $variant->update([
                            'price' => $newPrice,
                            'cj_stock_synced_at' => now(),
                        ]);

                        Log::warning('Variant price corruption repaired', [
                            'cj_vid' => $variant->cj_vid,
                            'old_price' => $variant->getOriginal('price'),
                            'new_price' => $newPrice,
                            'cost_price' => $variant->cost_price
                        ]);
                    }
                    $fixed++;
                    $progress->advance();
                }
            });

            $progress->finish();
        }

        if ($dryRun) {
            $this->info("\n[DRY RUN] Would fix {$fixed} price corruption issues");
        } else {
            $this->info("\n✅ Fixed {$fixed} price corruption issues");
        }

        return $fixed;
    }

    private function repairProductVariantRelationships(bool $dryRun, int $batchSize): int
    {
        $this->info("\n🔗 Repairing Product-Variant Relationships...");

        $fixed = 0;

        // Find orphaned variants
        $orphanedQuery = ProductVariant::whereNotNull('cj_vid')
            ->whereRaw('product_id NOT IN (SELECT id FROM products WHERE cj_pid IS NOT NULL)');

        $orphanedCount = $orphanedQuery->count();
        $this->info("Found {$orphanedCount} orphaned variants");

        if ($orphanedCount === 0) {
            $this->info("No relationship issues found.");
            return 0;
        }

        $progress = $this->output->createProgressBar($orphanedCount);
        $progress->start();

        $orphanedQuery->chunk($batchSize, function ($variants) use ($dryRun, &$fixed, $progress) {
            foreach ($variants as $variant) {
                if ($dryRun) {
                    $this->line("\n[DRY RUN] Would delete orphaned variant {$variant->cj_vid}");
                    $this->line("  Product ID: {$variant->product_id} (no CJ data)");
                    $this->line("  SKU: {$variant->sku}");
                } else {
                    // Delete orphaned variant
                    $variant->delete();
                    
                    Log::warning('Orphaned variant deleted', [
                        'cj_vid' => $variant->cj_vid,
                        'product_id' => $variant->product_id,
                        'sku' => $variant->sku
                    ]);
                }
                $fixed++;
                $progress->advance();
            }
        });

        $progress->finish();

        if ($dryRun) {
            $this->info("\n[DRY RUN] Would delete {$fixed} orphaned variants");
        } else {
            $this->info("\n✅ Deleted {$fixed} orphaned variants");
        }

        return $fixed;
    }
}
