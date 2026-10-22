<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class GenerateProductCodes extends Command
{
    protected $signature = 'products:generate-codes
                            {--dry-run : Preview changes without applying}
                            {--batch=100 : Number of products to process per batch}';

    protected $description = 'Generate product codes for existing products without codes';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');

        // Get products without codes
        $products = Product::whereNull('code')->orWhere('code', '')->get();
        $count = $products->count();

        if ($count === 0) {
            $this->info('✅ All products already have codes assigned.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} products without codes.");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made.');
            $this->table(
                ['ID', 'Name', 'Proposed Code'],
                $products->take(10)->map(fn (Product $p) => [
                    $p->id,
                    str($p->name)->limit(40),
                    Product::generateProductCode(),
                ])
            );

            if ($count > 10) {
                $this->info("... and " . ($count - 10) . " more products.");
            }

            return self::SUCCESS;
        }

        // Process in batches
        $processed = 0;
        $errors = 0;

        $this->info('Generating codes...');

        foreach ($products->chunk($batchSize) as $chunk) {
            foreach ($chunk as $product) {
                try {
                    // Generate unique code
                    $code = Product::generateProductCode();

                    // Update product
                    $product->update(['code' => $code]);

                    $processed++;
                    $this->info("✅ {$product->name} → {$code}");
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("❌ Failed for product ID {$product->id}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("Complete: {$processed} codes generated, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
