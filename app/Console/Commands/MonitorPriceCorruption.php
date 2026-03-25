<?php

namespace App\Console\Commands;

use App\Domain\Products\Services\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorPriceCorruption extends Command
{
    protected $signature = 'pricing:monitor-corruption 
                            {--threshold=1000 : Margin percentage threshold for corruption}
                            {--alert-threshold=5000 : Alert on margins above this percentage}
                            {--fix-auto : Automatically fix detected corruption}
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Monitor and detect price corruption in real-time';

    public function handle()
    {
        if (PricingService::usesNewEngine()) {
            $this->warn('pricing.use_new_engine is enabled. This legacy corruption monitor path is blocked to avoid mixing pricing engines.');

            return self::INVALID;
        }

        $threshold = (float) $this->option('threshold');
        $alertThreshold = (float) $this->option('alert-threshold');
        $fixAuto = $this->option('fix-auto');
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Monitoring for price corruption...');
        $this->line("Corruption Threshold: {$threshold}% margin");
        $this->line("Alert Threshold: {$alertThreshold}% margin");

        // Find corrupted products
        $corruptedProducts = DB::table('products')
            ->select('id', 'name', 'cost_price', 'selling_price', 'currency')
            ->selectRaw('ROUND(((selling_price - cost_price) / cost_price * 100), 2) as margin_percent')
            ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?)', [$threshold])
            ->where('cost_price', '>', 0)
            ->whereNotNull('cost_price')
            ->whereNotNull('selling_price')
            ->orderBy('margin_percent', 'desc')
            ->get();

        $criticalProducts = DB::table('products')
            ->select('id', 'name', 'cost_price', 'selling_price', 'currency')
            ->selectRaw('ROUND(((selling_price - cost_price) / cost_price * 100), 2) as margin_percent')
            ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?)', [$alertThreshold])
            ->where('cost_price', '>', 0)
            ->whereNotNull('cost_price')
            ->whereNotNull('selling_price')
            ->orderBy('margin_percent', 'desc')
            ->get();

        $corruptedCount = $corruptedProducts->count();
        $criticalCount = $criticalProducts->count();

        if ($corruptedCount === 0) {
            $this->info('✅ No price corruption detected!');
            return 0;
        }

        $this->warn("🚨 Found {$corruptedCount} products with corrupted margins:");
        
        // Show corrupted products
        $this->table(
            ['ID', 'Name', 'Cost', 'Price', 'Margin %'],
            $corruptedProducts->take(10)->map(function ($product) {
                return [
                    $product->id,
                    substr($product->name ?? '', 0, 50),
                    '$' . number_format($product->cost_price, 2),
                    '$' . number_format($product->selling_price, 2),
                    $product->margin_percent . '%'
                ];
            })
        );

        if ($criticalCount > 0) {
            $this->error("🚨 CRITICAL: {$criticalCount} products have EXTREME margins (>{$alertThreshold}%):");
            
            $this->table(
                ['ID', 'Name', 'Cost', 'Price', 'Margin %'],
                $criticalProducts->take(5)->map(function ($product) {
                    return [
                        $product->id,
                        substr($product->name ?? '', 0, 50),
                        '$' . number_format($product->cost_price, 2),
                        '$' . number_format($product->selling_price, 2),
                        $product->margin_percent . '%'
                    ];
                })
            );
        }

        // Calculate impact
        $totalRevenueImpact = $corruptedProducts->sum(function ($product) {
            return $product->selling_price - ($product->cost_price * 1.45); // Assuming 45% normal margin
        });

        $this->warn("💰 Revenue Impact: $" . number_format($totalRevenueImpact, 2));

        // Log critical alerts
        if ($criticalCount > 0) {
            Log::critical('CRITICAL PRICE CORRUPTION DETECTED', [
                'critical_products' => $criticalCount,
                'total_corrupted' => $corruptedCount,
                'revenue_impact' => $totalRevenueImpact,
                'alert_threshold' => $alertThreshold
            ]);
        }

        // Auto-fix if requested
        if ($fixAuto && !$dryRun) {
            $this->info('🔧 Auto-fixing corrupted prices...');
            $fixed = $this->autoFixPrices($corruptedProducts);
            $this->info("✅ Auto-fixed {$fixed} products");
        } elseif ($fixAuto && $dryRun) {
            $this->info('🔍 DRY RUN - Would auto-fix ' . $corruptedCount . ' products');
        }

        return $criticalCount > 0 ? 1 : 0;
    }

    private function autoFixPrices($products): int
    {
        $fixed = 0;
        $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();

        foreach ($products as $product) {
            try {
                $minSelling = $pricing->minSellingPrice((float) $product->cost_price);
                $newPrice = max($minSelling, $product->cost_price * 1.45); // 45% margin
                $newPrice = round($newPrice, 2);

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['selling_price' => $newPrice]);

                // Create margin log
                $logger = new \App\Services\ProductMarginLogger();
                $logger->logProduct(
                    \App\Domain\Products\Models\Product::find($product->id),
                    [
                        'source' => 'price_corruption_monitor',
                        'event' => 'corruption_auto_fixed',
                        'actor_type' => 'system',
                        'old_margin_percent' => $product->margin_percent,
                        'new_margin_percent' => 45.0,
                        'old_selling_price' => $product->selling_price,
                        'new_selling_price' => $newPrice,
                        'notes' => "Auto-fixed corruption from {$product->margin_percent}% to 45%"
                    ]
                );

                $fixed++;

            } catch (\Exception $e) {
                Log::error('Failed to auto-fix product', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $fixed;
    }
}
