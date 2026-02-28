<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use Illuminate\Console\Command;

class CjResyncAllProducts extends Command
{
    protected $signature = 'cj:resync-all 
                            {--limit=9000 : Number of products to sync}
                            {--batch-size=50 : Products per batch}
                            {--delay=1 : Delay between products in seconds}';

    protected $description = 'Re-sync all CJ products using the same method as Sync Now button';

    public function handle(CjProductImportService $importer): int
    {
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');

        $this->info("🚀 Re-syncing CJ products (limit: {$limit})");

        // Get all products with CJ PIDs
        $products = Product::whereNotNull('cj_pid')
            ->where('cj_pid', '!=', '')
            ->limit($limit)
            ->get();

        $total = $products->count();
        $this->info("📦 Found {$total} products to sync");

        if ($total === 0) {
            $this->warn('No products found with CJ PIDs');
            return self::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;
        $errors = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($products as $index => $product) {
            // Check memory usage before processing
            $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
            if ($memoryUsage > 512) { // If using more than 512MB
                $this->newLine();
                $this->warn("High memory usage ({$memoryUsage}MB), pausing for 10 seconds...");
                sleep(10);
                gc_collect_cycles(); // Force garbage collection
            }

            try {
                // Use the same method as "Sync Now" button
                $result = $importer->importByPid($product->cj_pid, [
                    'respectSyncFlag' => false, // Force sync all
                    'defaultSyncEnabled' => true,
                    'respectLocks' => false,
                    'syncVariants' => true, // Sync variants
                    'syncReviews' => false, // Skip reviews for speed
                    'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                ]);

                if ($result) {
                    $synced++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->warn("Error syncing {$product->cj_pid}: {$e->getMessage()}");
            }

            $bar->advance();

            // Add delay to avoid rate limiting and reduce server load
            if ($delay > 0 && ($index + 1) % $batchSize === 0) {
                sleep($delay);
                gc_collect_cycles(); // Clean up memory
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Sync complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Synced', $synced],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total', $total],
            ]
        );

        return self::SUCCESS;
    }
}
