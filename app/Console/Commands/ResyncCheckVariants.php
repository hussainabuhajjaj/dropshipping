<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Products\Services\CjProductImportService;

class ResyncCheckVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:resync-check-variants 
                            {--dry-run : Show what would be resynced without making changes}
                            {--batch-size=100 : Number of products to process in each batch}
                            {--limit= : Limit total number of products to process}
                            {--force : Force resync even if recently updated}
                            {--cj-pids= : Comma-separated CJ product IDs to resync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resync all imported products to check for CJ variants and apply new logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting product resync to check CJ variants...');
        
        $dryRun = $this->option('dry-run');
        $batchSize = $this->option('batch-size');
        $limit = $this->option('limit');
        $force = $this->option('force');
        $cjPids = $this->option('cj-pids');

        try {
            // Get products to resync
            $productsQuery = $this->getProductsQuery($cjPids, $force);
            
            if ($limit) {
                $productsQuery->limit($limit);
            }

            $totalProducts = $productsQuery->count();
            
            if ($totalProducts === 0) {
                $this->info('No products found to resync.');
                return 0;
            }

            $this->info("Found {$totalProducts} products to resync");

            // Show current state
            $this->showCurrentState();

            if (!$dryRun) {
                if ($this->confirm("Do you want to resync {$totalProducts} products? This may take a while.")) {
                    $this->performResync($productsQuery, $batchSize, $dryRun);
                } else {
                    $this->info('Operation cancelled.');
                    return 0;
                }
            } else {
                $this->info('This was a dry run. Use without --dry-run to perform resync.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            Log::error('Product resync command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    private function getProductsQuery($cjPids, $force)
    {
        $query = DB::table('products')
            ->whereNotNull('cj_pid')
            ->select(['id', 'cj_pid', 'name', 'updated_at'])
            ->orderBy('id'); // Add orderBy for chunk() method

        if ($cjPids) {
            $pidArray = explode(',', $cjPids);
            $query->whereIn('cj_pid', $pidArray);
        }

        if (!$force) {
            // Skip recently updated products (within last 24 hours)
            $query->where('updated_at', '<', now()->subHours(24));
        }

        return $query;
    }

    private function showCurrentState()
    {
        $this->newLine();
        $this->info('=== Current Product State ===');

        $stats = DB::table('products as p')
            ->leftJoin('product_variants as pv', 'p.id', '=', 'pv.product_id')
            ->selectRaw('
                COUNT(*) as total_products,
                COUNT(DISTINCT p.id) as unique_products,
                SUM(CASE WHEN pv.id IS NOT NULL THEN 1 ELSE 0 END) as products_with_variants,
                SUM(CASE WHEN pv.id IS NULL THEN 1 ELSE 0 END) as products_without_variants,
                COUNT(pv.id) as total_variants,
                SUM(CASE WHEN pv.title = "Default" OR pv.sku LIKE "DEFAULT-%" THEN 1 ELSE 0 END) as default_variants
            ')
            ->whereNotNull('p.cj_pid')
            ->first();

        $this->line("Total products with CJ PID: {$stats->unique_products}");
        $this->line("Products with variants: {$stats->products_with_variants}");
        $this->line("Products without variants: {$stats->products_without_variants}");
        $this->line("Total variants: {$stats->total_variants}");
        $this->line("Default variants: {$stats->default_variants}");
    }

    private function performResync($productsQuery, $batchSize, $dryRun)
    {
        $importService = app(CjProductImportService::class);
        $resyncedCount = 0;
        $errorCount = 0;
        $withVariantsCount = 0;
        $withoutVariantsCount = 0;

        $this->newLine();
        $this->info('=== Starting Resync ===');

        $productsQuery->chunk($batchSize, function ($products) use (&$resyncedCount, &$errorCount, &$withVariantsCount, &$withoutVariantsCount, $importService, $dryRun) {
            $this->line("Processing batch of " . $products->count() . " products...");

            foreach ($products as $product) {
                try {
                    if (!$dryRun) {
                        // Get current variant count before resync
                        $variantsBefore = DB::table('product_variants')
                            ->where('product_id', $product->id)
                            ->count();

                        // Resync the product
                        $result = $importService->importByPid($product->cj_pid, [
                            'forceUpdate' => true,
                            'skipExisting' => false,
                            'enrich' => true,
                        ]);

                        // Get variant count after resync
                        $variantsAfter = DB::table('product_variants')
                            ->where('product_id', $product->id)
                            ->count();

                        if ($variantsAfter > 0) {
                            $withVariantsCount++;
                            $this->line("  ✓ Product {$product->cj_pid}: {$variantsBefore} → {$variantsAfter} variants");
                        } else {
                            $withoutVariantsCount++;
                            $this->line("  ⚪ Product {$product->cj_pid}: Product-only (no variants)");
                        }
                    } else {
                        $this->line("  ○ Product {$product->cj_pid}: Would resync (dry run)");
                    }

                    $resyncedCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $this->line("  ✗ Product {$product->cj_pid}: Error - " . $e->getMessage());
                    Log::error("Product resync error", [
                        'cj_pid' => $product->cj_pid,
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Small delay to prevent API rate limiting
                if (!$dryRun && $resyncedCount % 10 === 0) {
                    usleep(500000); // 0.5 second delay every 10 products
                }
            }
        });

        $this->newLine();
        $this->info('=== Resync Complete ===');
        $this->info("Products processed: {$resyncedCount}");
        $this->info("Products with variants: {$withVariantsCount}");
        $this->info("Products without variants: {$withoutVariantsCount}");
        $this->info("Errors: {$errorCount}");

        // Show final state
        $this->showFinalState();
    }

    private function showFinalState()
    {
        $this->newLine();
        $this->info('=== Final Product State ===');

        $stats = DB::table('products as p')
            ->leftJoin('product_variants as pv', 'p.id', '=', 'pv.product_id')
            ->selectRaw('
                COUNT(*) as total_products,
                COUNT(DISTINCT p.id) as unique_products,
                SUM(CASE WHEN pv.id IS NOT NULL THEN 1 ELSE 0 END) as products_with_variants,
                SUM(CASE WHEN pv.id IS NULL THEN 1 ELSE 0 END) as products_without_variants,
                COUNT(pv.id) as total_variants,
                SUM(CASE WHEN pv.title = "Default" OR pv.sku LIKE "DEFAULT-%" THEN 1 ELSE 0 END) as default_variants
            ')
            ->whereNotNull('p.cj_pid')
            ->first();

        $this->line("Total products with CJ PID: {$stats->unique_products}");
        $this->line("Products with variants: {$stats->products_with_variants}");
        $this->line("Products without variants: {$stats->products_without_variants}");
        $this->line("Total variants: {$stats->total_variants}");
        $this->line("Default variants: {$stats->default_variants}");

        if ($stats->default_variants > 0) {
            $this->warn("⚠️  Still have {$stats->default_variants} default variants. Run: php artisan variants:fix-default --remove-duplicates");
        } else {
            $this->info("✅ No default variants found!");
        }
    }
}
