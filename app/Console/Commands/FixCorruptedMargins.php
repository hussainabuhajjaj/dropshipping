<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixCorruptedMargins extends Command
{
    protected $signature = 'pricing:fix-corrupted-margins 
                            {--dry-run : Show what will be fixed without making changes}
                            {--margin-threshold=1000 : Margin percentage threshold for corruption}
                            {--new-margin=45 : New margin percentage to apply}
                            {--backup : Create backup before fixing}
                            {--force : Force fix without confirmation}';

    protected $description = 'Fix products with corrupted margins (e.g., 99,014% margins)';

    public function handle()
    {
        $threshold = $this->option('margin-threshold');
        $newMargin = $this->option('new-margin');
        $dryRun = $this->option('dry-run');
        $backup = $this->option('backup');
        $force = $this->option('force');

        $this->info('🔍 Scanning for corrupted product margins...');
        $this->line("Threshold: {$threshold}% margin");
        $this->line("New margin will be: {$newMargin}%");
        
        // Find corrupted products
        $corruptedQuery = DB::table('products')
            ->select('id', 'name', 'cost_price', 'selling_price', 'currency')
            ->selectRaw('ROUND(((selling_price - cost_price) / cost_price * 100), 2) as margin_percent')
            ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?)', [$threshold])
            ->where('cost_price', '>', 0)
            ->orderBy('margin_percent', 'desc');

        $corrupted = $corruptedQuery->get();
        $count = $corrupted->count();

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
                WHERE ABS(((selling_price - cost_price) / cost_price * 100) > ?) AND cost_price > 0
            ", [$threshold]);
            
            $this->info("✅ Backup created: {$backupTable}");
        }

        // Fix the corrupted products
        $this->info('🔧 Fixing corrupted margins...');
        
        $fixed = DB::table('products')
            ->whereRaw('ABS(((selling_price - cost_price) / cost_price * 100) > ?) AND cost_price > 0', [$threshold])
            ->update(['selling_price' => DB::raw('cost_price * (1 + ' . $newMargin . ' / 100)')]);

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
                ['New Margin Applied', $newMargin . '%'],
                ['Backup Created', $backup ? 'Yes' : 'No']
            ]
        );

        return 0;
    }
}