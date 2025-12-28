<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Jobs\SyncCjVariantsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCjVariants extends Command
{
    protected $signature = 'cj:sync-variants {--product-id= : Sync a specific product ID (optional)}';

    protected $description = 'Sync variants and stock levels from CJ Dropshipping API for products';

    public function handle(): int
    {
        $productId = $this->option('product-id');

        if ($productId) {
            // Sync single product
            $product = Product::where('cj_pid', $productId)->orWhere('id', $productId)->first();

            if (!$product || !$product->cj_pid) {
                $this->error("Product not found or has no CJ PID: {$productId}");
                return self::FAILURE;
            }

            $this->info("Syncing variants for product: {$product->name} (CJ PID: {$product->cj_pid})");
            SyncCjVariantsJob::dispatch($product->cj_pid);
            $this->info('Dispatch queued successfully.');

            return self::SUCCESS;
        }

        // Sync all products with CJ PIDs and sync enabled
        $products = Product::whereNotNull('cj_pid')
            ->where('cj_sync_enabled', true)
            ->get();

        if ($products->isEmpty()) {
            $this->warn('No products found with CJ sync enabled.');
            return self::SUCCESS;
        }

        $this->info("Syncing {$products->count()} products...");

        $products->each(function (Product $product) {
            $this->line("  → {$product->name} (CJ PID: {$product->cj_pid})");
            SyncCjVariantsJob::dispatch($product->cj_pid);
        });

        $this->info('All sync jobs queued.');
        Log::info('CJ variant sync command executed', [
            'product_count' => $products->count(),
        ]);

        return self::SUCCESS;
    }
}
