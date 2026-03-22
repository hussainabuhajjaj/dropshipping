<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Filament\Resources\ProductResource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmergencyMarginDiagnosisCommand extends Command
{
    protected $signature = 'margins:diagnose-emergency 
                            {--fix : Attempt to fix issues automatically}
                            {--backup : Create backup before fixing}';

    protected $description = 'Diagnose and fix emergency margin issues (selling_price=0, missing cost_price)';

    public function handle()
    {
        $this->info('=== EMERGENCY MARGIN ISSUE DIAGNOSIS ===');
        $this->newLine();

        // Step 1: Diagnose the problem
        $this->step1Diagnose();

        // Step 2: Check recent changes
        $this->step2CheckRecentChanges();

        // Step 3: Create backup if requested
        if ($this->option('backup')) {
            $this->step3CreateBackup();
        }

        // Step 4: Attempt fixes if requested
        if ($this->option('fix')) {
            $this->step4FixIssues();
        }

        // Step 5: Generate report
        $this->step5GenerateReport();

        $this->newLine();
        $this->info('=== DIAGNOSIS COMPLETE ===');

        return 0;
    }

    private function step1Diagnose()
    {
        $this->info('STEP 1: DIAGNOSING THE PROBLEM');

        $zeroSellingProducts = Product::where('selling_price', 0)->get();
        $nullCostProducts = Product::whereNull('cost_price')->get();
        $zeroCostProducts = Product::where('cost_price', 0)->get();

        $this->line("Products with selling_price = 0: " . $zeroSellingProducts->count());
        $this->line("Products with NULL cost_price: " . $nullCostProducts->count());
        $this->line("Products with cost_price = 0: " . $zeroCostProducts->count());

        if ($zeroSellingProducts->count() > 0) {
            $this->newLine();
            $this->warn('First 5 products with selling_price = 0:');
            foreach ($zeroSellingProducts->take(5) as $product) {
                $this->line("ID: {$product->id}, Name: " . substr($product->name, 0, 40));
                $this->line("  Cost: " . ($product->cost_price ?? 'NULL'));
                $this->line("  Selling: {$product->selling_price}");
                $this->line("  Updated: {$product->updated_at}");
                $this->line("---");
            }
        }
    }

    private function step2CheckRecentChanges()
    {
        $this->newLine();
        $this->info('STEP 2: CHECKING RECENT CHANGES');

        $recentUpdates = Product::where('updated_at', '>=', now()->subHour())
            ->where(function($query) {
                $query->where('selling_price', 0)
                      ->orWhereNull('cost_price')
                      ->orWhere('cost_price', 0);
            })
            ->get();

        $this->line("Products with issues updated in the last hour: " . $recentUpdates->count());

        if ($recentUpdates->count() > 0) {
            $this->warn('Recent problematic updates detected!');
            foreach ($recentUpdates->take(3) as $product) {
                $this->line("  - ID {$product->id}: {$product->name}");
            }
        }
    }

    private function step3CreateBackup()
    {
        $this->newLine();
        $this->info('STEP 3: CREATING BACKUP');

        $backupFile = storage_path('backups/margin_issue_backup_' . date('Y-m-d_H-i-s') . '.sql');
        
        // Ensure backup directory exists
        $backupDir = dirname($backupFile);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s products > %s',
            escapeshellarg(config('database.username')),
            escapeshellarg(config('database.password')),
            escapeshellarg(config('database.host')),
            escapeshellarg(config('database.database')),
            escapeshellarg($backupFile)
        );

        $this->line("Creating backup to: {$backupFile}");
        
        $output = [];
        $return = 0;
        exec($command, $output, $return);

        if ($return === 0) {
            $this->info('✅ Backup created successfully');
        } else {
            $this->error('❌ Backup failed! Please create manual backup before proceeding.');
            if (!$this->confirm('Continue without backup? (NOT RECOMMENDED)')) {
                $this->error('Operation cancelled for safety.');
                exit(1);
            }
        }
    }

    private function step4FixIssues()
    {
        $this->newLine();
        $this->info('STEP 4: ATTEMPTING TO FIX ISSUES');

        $zeroSellingProducts = Product::where('selling_price', 0)->get();
        $recovered = 0;
        $failed = 0;

        $this->withProgressBar($zeroSellingProducts->count(), function ($bar) use ($zeroSellingProducts, &$recovered, &$failed) {
            foreach ($zeroSellingProducts as $product) {
                $bar->advance();
                
                if ($product->cj_last_payload) {
                    $payload = json_decode($product->cj_last_payload, true);
                    if ($payload && isset($payload['productCost']) && isset($payload['selling_price'])) {
                        $originalCost = $payload['productCost'];
                        $originalSelling = $payload['selling_price'];
                        
                        if ($originalCost > 0 && $originalSelling > 0) {
                            $product->update([
                                'cost_price' => $originalCost,
                                'selling_price' => $originalSelling
                            ]);
                            $recovered++;
                        } else {
                            $failed++;
                        }
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }
        });

        $this->newLine();
        $this->info('Recovery Summary:');
        $this->line("Recovered: {$recovered}");
        $this->line("Failed: {$failed}");

        if ($failed > 0) {
            $this->newLine();
            $this->warn("⚠️  {$failed} products still need manual attention");
        }
    }

    private function step5GenerateReport()
    {
        $this->newLine();
        $this->info('STEP 5: GENERATING RECOVERY REPORT');

        $report = [
            'timestamp' => now()->toISOString(),
            'issues_found' => [
                'zero_selling' => Product::where('selling_price', 0)->count(),
                'null_cost' => Product::whereNull('cost_price')->count(),
                'zero_cost' => Product::where('cost_price', 0)->count(),
            ],
            'total_products' => Product::count(),
            'healthy_products' => Product::whereNotNull('cost_price')
                ->where('cost_price', '>', 0)
                ->whereNotNull('selling_price')
                ->where('selling_price', '>', 0)
                ->count(),
        ];

        $reportFile = storage_path('logs/margin_emergency_report_' . date('Y-m-d_H-i-s') . '.json');
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));

        $this->line("Report saved to: {$reportFile}");
        
        $this->newLine();
        $this->info('Final Status:');
        $this->line("Total Products: {$report['total_products']}");
        $this->line("Healthy Products: {$report['healthy_products']}");
        $this->line("Products with selling_price = 0: {$report['issues_found']['zero_selling']}");
        $this->line("Products with NULL cost_price: {$report['issues_found']['null_cost']}");
        $this->line("Products with cost_price = 0: {$report['issues_found']['zero_cost']}");

        if ($report['issues_found']['zero_selling'] === 0 && 
            $report['issues_found']['null_cost'] === 0 && 
            $report['issues_found']['zero_cost'] === 0) {
            $this->newLine();
            $this->info('🎉 All issues have been resolved!');
        } else {
            $this->newLine();
            $this->warn('⚠️  Some issues still require attention');
        }
    }
}
