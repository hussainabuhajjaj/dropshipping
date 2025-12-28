<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCjInventoryHourly implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300; // 5 minutes per job
    public int $tries = 3;

    public function handle(): void
    {
        try {
            // Get all products with CJ sync enabled that haven't been synced in the last 30 minutes
            $products = Product::where('cj_sync_enabled', true)
                ->whereNotNull('cj_pid')
                ->where(function ($query) {
                    $query->whereNull('cj_synced_at')
                        ->orWhere('cj_synced_at', '<', now()->subMinutes(30));
                })
                ->get();

            if ($products->isEmpty()) {
                Log::debug('No products need CJ inventory sync');
                return;
            }

            Log::info('Starting hourly CJ inventory sync', ['product_count' => $products->count()]);

            // Dispatch sync job for each product
            $products->each(function (Product $product) {
                SyncCjVariantsJob::dispatch($product->cj_pid)
                    ->onQueue('cj-sync');
            });

            Log::info('Queued CJ inventory sync jobs', ['product_count' => $products->count()]);

        } catch (\Throwable $e) {
            Log::error('Failed to queue CJ inventory sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
