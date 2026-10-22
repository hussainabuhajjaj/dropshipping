<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ProductMarginLogger;

class FixCorruptedMargins extends Command
{
    protected $signature = 'pricing:fix-corrupted-margins 
                            {--dry-run : Show what will be fixed without making changes}
                            {--margin-threshold=1000 : Margin percentage threshold for corruption}
                            {--new-margin=45 : New margin percentage to apply}
                            {--backup : Create backup before fixing}
                            {--force : Force fix without confirmation}
                            {--include-variants : Also fix corrupted variants}';

    protected $description = 'Fix products with corrupted margins (e.g., 99,014% margins)';

    public function handle()
    {
        // Strict input validation
        $threshold = $this->validateThreshold($this->option('margin-threshold'));
        $newMargin = $this->validateMargin($this->option('new-margin'));
        $dryRun = $this->option('dry-run');
        $backup = $this->option('backup');
        $force = $this->option('force');
        $includeVariants = $this->option('include-variants');

        $this->info('🔍 Scanning for corrupted product margins...');
        $this->line("Threshold: {$threshold}% margin");
        $this->line("New margin will be: {$newMargin}%");
        
        // Find corrupted products with strict validation
        try {
            $corruptedQuery = DB::table('products')
                ->select('id', 'name', 'cost_price', 'selling_price', 'currency')
                ->selectRaw('ROUND(((selling_price - cost_price) / cost_price * 100), 2) as margin_percent')
                ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?)', [$threshold])
                ->where('cost_price', '>', 0)
                ->whereNotNull('cost_price')
                ->whereNotNull('selling_price')
                ->orderBy('margin_percent', 'desc');

            $corrupted = $corruptedQuery->get();
            $count = $corrupted->count();
        } catch (\Exception $e) {
            $this->error('❌ Database query failed: ' . $e->getMessage());
            Log::error('FixCorruptedMargins query failed', ['error' => $e->getMessage()]);
            return 1;
        }

        if ($count === 0) {
            $this->info('✅ No corrupted margins found!');
            return 0;
        }

        $this->warn("🚨 Found {$count} products with corrupted margins:");
        
        // Show top 10 corrupted products
        $this->table(
            ['ID', 'Name', 'Cost', 'Price', 'Margin %'],
            $corrupted->take(10)->map(function ($product) {
                return [
                    $product->id,
                    substr($product->name ?? '', 0, 50),
                    '$' . number_format($product->cost_price, 2),
                    '$' . number_format($product->selling_price, 2),
                    $product->margin_percent . '%'
                ];
            })
        );

        
        if ($count > 10) {
            $this->line("... and " . ($count - 10) . " more products");
        }

        // Calculate impact
        $totalRevenueLoss = $corrupted->sum(function ($product) use ($newMargin) {
            if ($product->cost_price <= 0) {
                return 0;
            }
            return $product->selling_price - ($product->cost_price * (1 + $newMargin / 100));
        });

        $this->warn("💰 Revenue impact: $" . number_format($totalRevenueLoss, 2));

        if ($dryRun) {
            $this->info('🔍 DRY RUN - No changes made');
            return 0;
        }

        // Confirm before proceeding
        if (!$force) {
            if (!$this->confirm("⚠️  Do you want to fix these {$count} products?")) {
                $this->info('❌ Operation cancelled');
                return 0;
            }
        }

        // Create backup if requested
        $backupTable = null;
        if ($backup) {
            $this->info('💾 Creating backup...');
            $backupTable = 'products_margin_backup_' . date('Y_m_d_His');
            
            DB::statement("
                CREATE TABLE {$backupTable} AS 
                SELECT * FROM products 
                WHERE cost_price > 0 AND ABS(((selling_price - cost_price) * 100 / cost_price) > ?
            ", [$threshold]);
            
            $this->info("✅ Backup created: {$backupTable}");
        }

        // Fix the corrupted products
        $this->info('🔧 Fixing corrupted margins...');
        
        // Get products to fix for individual processing
        $productsToFix = DB::table('products')
            ->select('id', 'name', 'cost_price', 'selling_price', 'currency')
            ->selectRaw('ROUND(((selling_price - cost_price) / cost_price * 100), 2) as margin_percent')
            ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?) AND cost_price > 0', [$threshold])
            ->get();
        
        $fixed = 0;
        $marginLogger = new ProductMarginLogger();
        $totalProducts = $productsToFix->count();
        
        $this->withProgressBar($totalProducts, function () use ($productsToFix, &$fixed, $marginLogger, $newMargin, $threshold) {
            foreach ($productsToFix as $product) {
                try {
                    // Validate product data before processing
                    if (!$this->validateProductData($product)) {
                        Log::warning('Skipping invalid product data', ['product_id' => $product->id ?? 'unknown']);
                        continue;
                    }
                    
                    $oldSellingPrice = (float) $product->selling_price;
                    $newSellingPrice = $this->calculateNewSellingPrice((float) $product->cost_price, $newMargin);
                    $oldMargin = round(((($oldSellingPrice - $product->cost_price) / $product->cost_price) * 100), 2);
                    
                    // Update the product with transaction safety
                    DB::transaction(function () use ($product, $newSellingPrice) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->where('cost_price', $product->cost_price) // Ensure no concurrent modification
                            ->update(['selling_price' => $newSellingPrice]);
                    });
                    
                    // Create margin log record
                    $marginLogger->logProduct(
                        \App\Domain\Products\Models\Product::find($product->id),
                        [
                            'source' => 'fix_corrupted_margins_command',
                            'event' => 'margin_fixed',
                            'actor_type' => 'system',
                            'old_margin_percent' => $oldMargin,
                            'new_margin_percent' => $newMargin,
                            'old_selling_price' => $oldSellingPrice,
                            'new_selling_price' => $newSellingPrice,
                            'notes' => "Fixed corrupted margin from {$oldMargin}% to {$newMargin}% (threshold: {$threshold}%)"
                        ]
                    );
                    
                    $fixed++;
                    
                } catch (\Exception $e) {
                    Log::error('Failed to fix product margin', [
                        'product_id' => $product->id ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->warn("⚠️ Failed to fix product ID " . ($product->id ?? 'unknown') . ": " . $e->getMessage());
                }
            }
        });
        
        $this->newLine();

        // Handle variants if requested
        $variantsFixed = 0;
        if ($includeVariants) {
            $this->info('🔍 Scanning for corrupted variant margins...');
            
            // Find corrupted variants
            $corruptedVariantsQuery = DB::table('product_variants')
                ->select('id', 'product_id', 'title', 'cost_price', 'price', 'currency')
                ->selectRaw('ROUND(((price - cost_price) / cost_price * 100), 2) as margin_percent')
                ->whereRaw('ABS(((price - cost_price) / cost_price * 100) > ?)', [$threshold])
                ->where('cost_price', '>', 0)
                ->whereNotNull('cost_price')
                ->whereNotNull('price')
                ->orderBy('margin_percent', 'desc');

            $corruptedVariants = $corruptedVariantsQuery->get();
            $variantCount = $corruptedVariants->count();

            if ($variantCount > 0) {
                $this->warn("🚨 Found {$variantCount} variants with corrupted margins:");
                
                // Show top 10 corrupted variants
                $this->table(
                    ['ID', 'Product ID', 'Title', 'Cost', 'Price', 'Margin %'],
                    $corruptedVariants->take(10)->map(function ($variant) {
                        return [
                            $variant->id,
                            $variant->product_id,
                            substr($variant->title ?? '', 0, 40),
                            '$' . number_format($variant->cost_price, 2),
                            '$' . number_format($variant->price, 2),
                            $variant->margin_percent . '%'
                        ];
                    })
                );

                if (!$dryRun) {
                    $this->info('🔧 Fixing corrupted variant margins...');
                    
                    foreach ($corruptedVariants as $variant) {
                        try {
                            $newVariantPrice = $this->calculateNewSellingPrice((float) $variant->cost_price, $newMargin);
                            $oldVariantPrice = (float) $variant->price;
                            $oldVariantMargin = round(((($oldVariantPrice - $variant->cost_price) / $variant->cost_price) * 100), 2);
                            
                            // Update the variant with transaction safety
                            DB::transaction(function () use ($variant, $newVariantPrice) {
                                DB::table('product_variants')
                                    ->where('id', $variant->id)
                                    ->where('cost_price', $variant->cost_price)
                                    ->update(['price' => $newVariantPrice]);
                            });
                            
                            // Create variant margin log record
                            $marginLogger->logVariant(
                                \App\Domain\Products\Models\ProductVariant::find($variant->id),
                                [
                                    'source' => 'fix_corrupted_margins_command',
                                    'event' => 'variant_margin_fixed',
                                    'actor_type' => 'system',
                                    'old_margin_percent' => $oldVariantMargin,
                                    'new_margin_percent' => $newMargin,
                                    'old_selling_price' => $oldVariantPrice,
                                    'new_selling_price' => $newVariantPrice,
                                    'notes' => "Fixed corrupted variant margin from {$oldVariantMargin}% to {$newMargin}% (threshold: {$threshold}%)"
                                ]
                            );
                            
                            $variantsFixed++;
                            
                        } catch (\Exception $e) {
                            Log::error('Failed to fix variant margin', [
                                'variant_id' => $variant->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            $this->warn("⚠️ Failed to fix variant ID " . $variant->id . ": " . $e->getMessage());
                        }
                    }
                    
                    $this->info("✅ Successfully fixed {$variantsFixed} variants!");
                }
            } else {
                $this->info('✅ No corrupted variant margins found!');
            }
        }

        // Log the operation
        Log::info('Fixed corrupted product margins', [
            'products_fixed' => $fixed,
            'threshold' => $threshold,
            'new_margin' => $newMargin,
            'backup_table' => $backupTable ?? null,
            'backup_created' => $backup ?? false
        ]);



        // Verify fix
        $remaining = DB::table('products')
            ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?) AND cost_price > 0', [$threshold])
            ->count();

        if ($remaining === 0) {
            $this->info("✅ Successfully fixed all {$fixed} products!");
        } else {
            $this->warn("⚠️  Fixed {$fixed} products, but {$remaining} still have issues");
        }

        // Show summary
        $this->table(
            ['Metric', 'Value'],
            [
                ['Products Scanned', DB::table('products')->count()],
                ['Products Fixed', $fixed],
                ['Products Remaining', $remaining],
                ['Variants Fixed', $includeVariants ? $variantsFixed : 'N/A'],
                ['New Margin Applied', $newMargin . '%'],
                ['Backup Created', $backup ? 'Yes' : 'No'],
                ['Margin Logs Created', $fixed > 0 ? 'Yes (' . $fixed . ' entries)' : 'No']
            ]
        );

        return 0;
    }

    /**
     * Validate margin threshold input
     */
    private function validateThreshold($threshold): float
    {
        $threshold = filter_var($threshold, FILTER_VALIDATE_FLOAT);
        
        if ($threshold === false || $threshold < 0 || $threshold > 100000) {
            $this->error('❌ Invalid threshold. Must be a number between 0 and 100000.');
            exit(1);
        }
        
        return (float) $threshold;
    }

    /**
     * Validate new margin input
     */
    private function validateMargin($margin): float
    {
        $margin = filter_var($margin, FILTER_VALIDATE_FLOAT);
        
        if ($margin === false || $margin < 0 || $margin > 1000) {
            $this->error('❌ Invalid margin. Must be a number between 0 and 1000%.');
            exit(1);
        }
        
        return (float) $margin;
    }

    /**
     * Validate product data before processing
     */
    private function validateProductData($product): bool
    {
        if (!isset($product->id) || !is_numeric($product->id)) {
            return false;
        }
        
        if (!isset($product->cost_price) || !is_numeric($product->cost_price) || $product->cost_price <= 0) {
            return false;
        }
        
        if (!isset($product->selling_price) || !is_numeric($product->selling_price) || $product->selling_price < 0) {
            return false;
        }
        
        return true;
    }

    /**
     * Calculate new selling price with strict validation
     */
    private function calculateNewSellingPrice(float $costPrice, float $margin): float
    {
        if ($costPrice <= 0) {
            throw new \InvalidArgumentException('Cost price must be greater than 0');
        }
        
        if ($margin < 0) {
            throw new \InvalidArgumentException('Margin cannot be negative');
        }
        
        $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
        $minSelling = $pricing->minSellingPrice($costPrice);
        $calculatedPrice = $costPrice * (1 + $margin / 100);
        $newPrice = max($calculatedPrice, $minSelling);
        
        return round($newPrice, 2);
    }
}