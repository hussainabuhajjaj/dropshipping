<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ValidateCjDataIntegrity extends Command
{
    protected $signature = 'cj:validate-data-integrity
        {--quick : Run quick validation only}
        {--detailed : Run detailed validation with samples}
        {--export : Export results to file}';

    protected $description = 'Validate CJ data integrity and generate repair report';

    public function handle(): int
    {
        $this->info('🔍 CJ Data Integrity Validation');
        $this->info('==============================');

        $quick = $this->option('quick');
        $detailed = $this->option('detailed');
        $export = $this->option('export');

        $results = [
            'timestamp' => now()->toISOString(),
            'validation_type' => $quick ? 'quick' : ($detailed ? 'detailed' : 'standard'),
            'issues' => [],
            'metrics' => [],
            'samples' => [],
        ];

        // Basic metrics
        $this->validateBasicMetrics($results);

        if (!$quick) {
            // Detailed validation
            $this->validateInventoryConsistency($results);
            $this->validatePriceIntegrity($results);
            $this->validateRelationshipIntegrity($results);
            $this->validateDataFreshness($results);
        }

        if ($detailed) {
            $this->generateSamples($results);
        }

        // Generate report
        $this->generateReport($results, $export);

        // Summary
        $this->displaySummary($results);

        return ($results['metrics']['total_issues'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function validateBasicMetrics(array &$results): void
    {
        $this->info('📊 Validating basic metrics...');

        $metrics = [
            'products_with_cj_data' => Product::whereNotNull('cj_pid')->count(),
            'variants_with_cj_data' => ProductVariant::whereNotNull('cj_vid')->count(),
            'products_without_variants' => Product::whereNotNull('cj_pid')->whereDoesntHave('variants')->count(),
            'variants_without_products' => ProductVariant::whereNotNull('cj_vid')->whereDoesntHave('product')->count(),
        ];

        $results['metrics'] = array_merge($results['metrics'], $metrics);

        $this->table(['Metric', 'Count'], array_map(null, array_keys($metrics), array_values($metrics)));
    }

    private function validateInventoryConsistency(array &$results): void
    {
        $this->info('📦 Validating inventory consistency...');

        // Products with zero stock but variants have stock
        $inventoryIssues = Product::whereNotNull('cj_pid')
            ->where('stock_on_hand', 0)
            ->whereHas('variants', function ($q) {
                $q->where('stock_on_hand', '>', 0);
            })
            ->with(['variants' => function ($q) {
                $q->where('stock_on_hand', '>', 0)->limit(3);
            }])
            ->get();

        $results['metrics']['inventory_inconsistencies'] = $inventoryIssues->count();

        if ($inventoryIssues->isNotEmpty()) {
            $results['issues']['inventory_inconsistencies'] = $inventoryIssues->take(10)->map(function ($product) {
                return [
                    'cj_pid' => $product->cj_pid,
                    'product_name' => substr($product->name, 0, 50),
                    'product_stock' => $product->stock_on_hand,
                    'variant_count' => $product->variants()->where('stock_on_hand', '>', 0)->count(),
                    'total_variant_stock' => $product->variants()->sum('stock_on_hand'),
                ];
            })->toArray();
        }

        $this->line("Found {$inventoryIssues->count()} products with inventory inconsistencies");
    }

    private function validatePriceIntegrity(array &$results): void
    {
        $this->info('💰 Validating price integrity...');

        // Products with extreme prices
        $extremePriceProducts = Product::whereNotNull('cj_pid')
            ->whereRaw('selling_price > cost_price * 10')
            ->orWhereRaw('selling_price < cost_price * 0.5')
            ->get();

        $results['metrics']['extreme_price_products'] = $extremePriceProducts->count();

        if ($extremePriceProducts->isNotEmpty()) {
            $results['issues']['extreme_price_products'] = $extremePriceProducts->take(5)->map(function ($product) {
                return [
                    'cj_pid' => $product->cj_pid,
                    'product_name' => substr($product->name, 0, 50),
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'markup_ratio' => $product->cost_price > 0 ? round($product->selling_price / $product->cost_price, 2) : 'N/A',
                ];
            })->toArray();
        }

        // Variants with extreme prices
        $extremePriceVariants = ProductVariant::whereNotNull('cj_vid')
            ->whereRaw('price > cost_price * 10')
            ->orWhereRaw('price < cost_price * 0.5')
            ->get();

        $results['metrics']['extreme_price_variants'] = $extremePriceVariants->count();

        if ($extremePriceVariants->isNotEmpty()) {
            $results['issues']['extreme_price_variants'] = $extremePriceVariants->take(5)->map(function ($variant) {
                return [
                    'cj_vid' => $variant->cj_vid,
                    'product_cj_pid' => $variant->product->cj_pid ?? 'N/A',
                    'sku' => $variant->sku,
                    'cost_price' => $variant->cost_price,
                    'price' => $variant->price,
                    'markup_ratio' => $variant->cost_price > 0 ? round($variant->price / $variant->cost_price, 2) : 'N/A',
                ];
            })->toArray();
        }

        $this->line("Found {$extremePriceProducts->count()} products and {$extremePriceVariants->count()} variants with extreme prices");
    }

    private function validateRelationshipIntegrity(array &$results): void
    {
        $this->info('🔗 Validating relationship integrity...');

        // Orphaned variants
        $orphanedVariants = ProductVariant::whereNotNull('cj_vid')
            ->whereRaw('product_id NOT IN (SELECT id FROM products WHERE cj_pid IS NOT NULL)')
            ->get();

        $results['metrics']['orphaned_variants'] = $orphanedVariants->count();

        if ($orphanedVariants->isNotEmpty()) {
            $results['issues']['orphaned_variants'] = $orphanedVariants->take(5)->map(function ($variant) {
                return [
                    'cj_vid' => $variant->cj_vid,
                    'product_id' => $variant->product_id,
                    'sku' => $variant->sku,
                    'title' => substr($variant->title, 0, 50),
                ];
            })->toArray();
        }

        // Variants with missing CJ data
        $variantsMissingCjData = ProductVariant::whereNull('cj_vid')
            ->whereHas('product', function ($q) {
                $q->whereNotNull('cj_pid');
            })
            ->count();

        $results['metrics']['variants_missing_cj_data'] = $variantsMissingCjData;

        $this->line("Found {$orphanedVariants->count()} orphaned variants and {$variantsMissingCjData} variants missing CJ data");
    }

    private function validateDataFreshness(array &$results): void
    {
        $this->info('🕒 Validating data freshness...');

        $now = now();
        
        // Products not synced recently
        $staleProducts = Product::whereNotNull('cj_pid')
            ->where(function ($q) use ($now) {
                $q->whereNull('cj_synced_at')
                  ->orWhere('cj_synced_at', '<', $now->subDays(7));
            })
            ->count();

        $results['metrics']['stale_products'] = $staleProducts;

        // Variants not synced recently
        $staleVariants = ProductVariant::whereNotNull('cj_vid')
            ->where(function ($q) use ($now) {
                $q->whereNull('cj_stock_synced_at')
                  ->orWhere('cj_stock_synced_at', '<', $now->subDays(7));
            })
            ->count();

        $results['metrics']['stale_variants'] = $staleVariants;

        $this->line("Found {$staleProducts} stale products and {$staleVariants} stale variants (older than 7 days)");
    }

    private function generateSamples(array &$results): void
    {
        $this->info('📋 Generating samples...');

        // Sample of healthy products
        $healthyProducts = Product::whereNotNull('cj_pid')
            ->where('stock_on_hand', '>', 0)
            ->whereRaw('selling_price BETWEEN cost_price * 1.1 AND cost_price * 5')
            ->whereHas('variants')
            ->where('cj_synced_at', '>', now()->subDays(7))
            ->take(3)
            ->get();

        $results['samples']['healthy_products'] = $healthyProducts->map(function ($product) {
            return [
                'cj_pid' => $product->cj_pid,
                'name' => substr($product->name, 0, 50),
                'stock_on_hand' => $product->stock_on_hand,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'variant_count' => $product->variants()->count(),
                'cj_synced_at' => $product->cj_synced_at?->toISOString(),
            ];
        })->toArray();

        // Sample of problematic products
        $problematicProducts = Product::whereNotNull('cj_pid')
            ->where(function ($q) {
                $q->where('stock_on_hand', 0)
                  ->orWhereRaw('selling_price > cost_price * 10')
                  ->orWhereNull('cj_synced_at');
            })
            ->take(3)
            ->get();

        $results['samples']['problematic_products'] = $problematicProducts->map(function ($product) {
            return [
                'cj_pid' => $product->cj_pid,
                'name' => substr($product->name, 0, 50),
                'issues' => [
                    'zero_stock' => $product->stock_on_hand == 0,
                    'extreme_price' => $product->cost_price > 0 && $product->selling_price > ($product->cost_price * 10),
                    'not_synced' => $product->cj_synced_at === null,
                ],
            ];
        })->toArray();
    }

    private function generateReport(array $results, bool $export): void
    {
        // Calculate total issues from all issue types
        $totalIssues = 0;
        foreach ($results['issues'] as $issueType => $issues) {
            $count = is_array($issues) ? count($issues) : 0;
            $totalIssues += $count;
        }
        
        $results['metrics']['total_issues'] = $totalIssues;

        if ($export) {
            $filename = 'cj-validation-report-' . date('Y-m-d-H-i-s') . '.json';
            $filepath = storage_path("logs/{$filename}");
            file_put_contents($filepath, json_encode($results, JSON_PRETTY_PRINT));
            $this->info("Report exported to: {$filepath}");
        }
    }

    private function displaySummary(array $results): void
    {
        $this->info("\n📊 VALIDATION SUMMARY");
        $this->info("====================");

        // Metrics table
        $metrics = $results['metrics'];
        $this->table(['Metric', 'Count'], [
            ['Products with CJ data', $metrics['products_with_cj_data']],
            ['Variants with CJ data', $metrics['variants_with_cj_data']],
            ['Inventory inconsistencies', $metrics['inventory_inconsistencies'] ?? 0],
            ['Products with extreme prices', $metrics['extreme_price_products'] ?? 0],
            ['Variants with extreme prices', $metrics['extreme_price_variants'] ?? 0],
            ['Orphaned variants', $metrics['orphaned_variants'] ?? 0],
            ['Stale products', $metrics['stale_products'] ?? 0],
            ['Stale variants', $metrics['stale_variants'] ?? 0],
            ['Total issues', $metrics['total_issues'] ?? 0],
        ]);

        // Issues found
        $totalIssues = $metrics['total_issues'] ?? 0;
        if ($totalIssues > 0) {
            $this->error("\n⚠️  ISSUES FOUND:");
            
            foreach ($results['issues'] as $issueType => $issues) {
                $count = is_array($issues) ? count($issues) : 0;
                if ($count > 0) {
                    $this->line("  • {$issueType}: {$count}");
                }
            }

            $this->info("\n🔧 RECOMMENDED ACTIONS:");
            
            if ($metrics['inventory_inconsistencies'] > 0) {
                $this->line("  • Run: php artisan cj:repair-data-corruption --fix-inventory");
            }
            
            if (($metrics['extreme_price_products'] + $metrics['extreme_price_variants']) > 0) {
                $this->line("  • Run: php artisan cj:repair-data-corruption --fix-prices");
            }
            
            if ($metrics['orphaned_variants'] > 0) {
                $this->line("  • Run: php artisan cj:repair-data-corruption --fix-relationships");
            }
            
            if (($metrics['stale_products'] + $metrics['stale_variants']) > 0) {
                $this->line("  • Run: php artisan cj:sync-realtime-inventory");
            }
        } else {
            $this->info("\n✅ NO ISSUES FOUND - All data is healthy!");
        }

        // Next steps
        $this->info("\n🎯 NEXT STEPS:");
        if ($totalIssues > 0) {
            $this->line("  1. Run the recommended repair commands above");
            $this->line("  2. Re-run validation: php artisan cj:validate-data-integrity");
            $this->line("  3. Use the live repair script: ./cj-repair-live-server.sh");
        } else {
            $this->line("  1. Continue monitoring data integrity");
            $this->line("  2. Schedule regular validation checks");
            $this->line("  3. Set up automated monitoring");
        }
    }
}
