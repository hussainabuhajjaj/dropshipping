<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixDefaultVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'variants:fix-default 
                            {--dry-run : Show what would be updated without making changes}
                            {--remove-duplicates : Remove default variants when proper variants exist}
                            {--force : Force update even if variants already have proper names}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix variants with "Default" names and "DEFAULT-" SKUs from import process';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting fix for default variants...');
        
        $dryRun = $this->option('dry-run');
        $removeDuplicates = $this->option('remove-duplicates');
        $force = $this->option('force');

        try {
            // Find variants with default names or SKUs
            $variantsQuery = DB::table('product_variants')
                ->where(function($query) {
                    $query->where('title', 'Default')
                          ->orWhere('sku', 'LIKE', 'DEFAULT-%')
                          ->orWhere('title', 'LIKE', '%default%')
                          ->orWhere('title', 'LIKE', '%callback%');
                });

            $totalVariants = $variantsQuery->count();
            
            if ($totalVariants === 0) {
                $this->info('No default variants found.');
                return 0;
            }

            $this->info("Found {$totalVariants} default variants");

            // Show detailed breakdown
            $this->getVariantBreakdown();

            // Show product analysis
            $this->analyzeProductVariants();

            if (!$dryRun) {
                if ($this->confirm('Do you want to proceed with the fixes?')) {
                    $this->performFixes($removeDuplicates, $force);
                } else {
                    $this->info('Operation cancelled.');
                    return 0;
                }
            } else {
                $this->warn('This was a dry run. Use without --dry-run to apply changes.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            Log::error('Default variants fix command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    private function getVariantBreakdown()
    {
        $this->newLine();
        $this->info('=== Variant Breakdown ===');

        $breakdown = DB::table('product_variants')
            ->selectRaw('
                SUM(CASE WHEN title = "Default" THEN 1 ELSE 0 END) as title_default,
                SUM(CASE WHEN sku LIKE "DEFAULT-%" THEN 1 ELSE 0 END) as sku_default,
                SUM(CASE WHEN title LIKE "%default%" THEN 1 ELSE 0 END) as title_contains_default,
                SUM(CASE WHEN title LIKE "%callback%" THEN 1 ELSE 0 END) as title_contains_callback
            ')
            ->where(function($query) {
                $query->where('title', 'Default')
                      ->orWhere('sku', 'LIKE', 'DEFAULT-%')
                      ->orWhere('title', 'LIKE', '%default%')
                      ->orWhere('title', 'LIKE', '%callback%');
            })
            ->first();

        $this->line("Variants with 'Default' title: {$breakdown->title_default}");
        $this->line("Variants with 'DEFAULT-' SKU: {$breakdown->sku_default}");
        $this->line("Variants containing 'default' in title: {$breakdown->title_contains_default}");
        $this->line("Variants containing 'callback' in title: {$breakdown->title_contains_callback}");
    }

    private function analyzeProductVariants()
    {
        $this->newLine();
        $this->info('=== Product Analysis ===');

        $productAnalysis = DB::table('product_variants as pv')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->selectRaw('
                pv.product_id,
                p.name as product_name,
                COUNT(pv.id) as total_variants,
                SUM(CASE WHEN pv.title = "Default" OR pv.sku LIKE "DEFAULT-%" THEN 1 ELSE 0 END) as default_variants,
                SUM(CASE WHEN pv.title != "Default" AND pv.sku NOT LIKE "DEFAULT-%" THEN 1 ELSE 0 END) as proper_variants
            ')
            ->where(function($query) {
                $query->where('pv.title', 'Default')
                      ->orWhere('pv.sku', 'LIKE', 'DEFAULT-%')
                      ->orWhere('pv.title', 'LIKE', '%default%')
                      ->orWhere('pv.title', 'LIKE', '%callback%');
            })
            ->groupBy('pv.product_id', 'p.name')
            ->having('default_variants', '>', 0)
            ->orderBy('default_variants', 'desc')
            ->orderBy('total_variants', 'desc')
            ->limit(10)
            ->get();

        if ($productAnalysis->isEmpty()) {
            $this->info('No products with default variants found.');
            return;
        }

        $this->line('Top 10 products with default variants:');
        foreach ($productAnalysis as $product) {
            $productName = $product->product_name ?? 'Unknown Product';
            $this->line("  Product {$product->product_id} ({$productName}): {$product->default_variants} default, {$product->proper_variants} proper variants");
        }

        $totalAffected = $productAnalysis->sum('default_variants');
        $this->info("Total default variants across all products: {$totalAffected}");
    }

    private function performFixes($removeDuplicates, $force)
    {
        $this->newLine();
        $this->info('=== Performing Fixes ===');

        $updatedCount = 0;
        $deletedCount = 0;

        // Step 1: Update standalone default variants (products that only have default variants)
        $standaloneVariants = DB::table('product_variants as pv')
            ->where(function($query) {
                $query->where('pv.title', 'Default')
                      ->orWhere('pv.sku', 'LIKE', 'DEFAULT-%');
            })
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('product_variants as pv2')
                      ->whereRaw('pv2.product_id = pv.product_id')
                      ->where(function($subQuery) {
                          $subQuery->where('pv2.title', '!=', 'Default')
                                 ->orWhere('pv2.sku', 'NOT LIKE', 'DEFAULT-%');
                      })
                      ->whereRaw('pv2.id != pv.id');
            })
            ->get();

        $this->info("Updating " . $standaloneVariants->count() . " standalone default variants...");
        
        $progressBar = $this->output->createProgressBar($standaloneVariants->count());
        $progressBar->start();

        foreach ($standaloneVariants as $variant) {
            $updates = [
                'title' => "Variant of Product {$variant->product_id}",
                'sku' => "VAR-{$variant->product_id}-{$variant->id}",
                'updated_at' => now(),
            ];

            DB::table('product_variants')
                ->where('id', $variant->id)
                ->update($updates);

            $updatedCount++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Step 2: Remove duplicate default variants if requested
        if ($removeDuplicates) {
            $duplicateVariants = DB::table('product_variants as pv')
                ->where(function($query) {
                    $query->where('pv.title', 'Default')
                          ->orWhere('pv.sku', 'LIKE', 'DEFAULT-%');
                })
                ->whereExists(function($query) {
                    $query->select(DB::raw(1))
                          ->from('product_variants as pv2')
                          ->whereRaw('pv2.product_id = pv.product_id')
                          ->where(function($subQuery) {
                              $subQuery->where('pv2.title', '!=', 'Default')
                                     ->orWhere('pv2.sku', 'NOT LIKE', 'DEFAULT-%');
                          })
                          ->whereRaw('pv2.id != pv.id');
                })
                ->get();

            $this->info("Removing " . $duplicateVariants->count() . " duplicate default variants...");
            
            if ($duplicateVariants->count() > 0) {
                $progressBar = $this->output->createProgressBar($duplicateVariants->count());
                $progressBar->start();

                foreach ($duplicateVariants as $variant) {
                    DB::table('product_variants')
                        ->where('id', $variant->id)
                        ->delete();

                    $deletedCount++;
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine();
            }
        }

        $this->newLine();
        $this->info('=== Fix Complete ===');
        $this->info("Updated variants: {$updatedCount}");
        $this->info("Deleted variants: {$deletedCount}");

        // Show final verification
        $this->showFinalVerification();
    }

    private function showFinalVerification()
    {
        $this->newLine();
        $this->info('=== Final Verification ===');

        $remainingIssues = DB::table('product_variants')
            ->selectRaw('
                SUM(CASE WHEN title = "Default" THEN 1 ELSE 0 END) as remaining_default_titles,
                SUM(CASE WHEN sku LIKE "DEFAULT-%" THEN 1 ELSE 0 END) as remaining_default_skus,
                SUM(CASE WHEN title LIKE "%callback%" THEN 1 ELSE 0 END) as remaining_callback_titles
            ')
            ->first();

        $this->line("Remaining variants with 'Default' title: {$remainingIssues->remaining_default_titles}");
        $this->line("Remaining variants with 'DEFAULT-' SKU: {$remainingIssues->remaining_default_skus}");
        $this->line("Remaining variants with 'callback' in title: {$remainingIssues->remaining_callback_titles}");

        if ($remainingIssues->remaining_default_titles == 0 && 
            $remainingIssues->remaining_default_skus == 0 && 
            $remainingIssues->remaining_callback_titles == 0) {
            $this->info('✅ All default variants have been fixed!');
        } else {
            $this->warn('⚠️  Some default variants remain. You may need to run with --force or check manually.');
        }
    }
}
