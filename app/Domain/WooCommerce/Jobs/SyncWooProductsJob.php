<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWooProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(
        private readonly array $productIds = [],
    ) {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(WooCommerceProductSyncService $syncService): void
    {
        if (! config('woocommerce.enabled', false)) {
            Log::info('WooCommerce sync skipped: integration disabled');

            return;
        }

        $query = Product::query()
            ->where('is_active', true)
            ->whereNotNull('selling_price');

        if ($this->productIds !== []) {
            $query->whereIn('id', $this->productIds);
        }

        $count = 0;
        $errors = 0;

        foreach ($query->cursor() as $product) {
            try {
                $result = $syncService->syncProduct($product);

                if ($result->success) {
                    $count++;
                } else {
                    $errors++;
                    Log::warning('WooCommerce product sync failed', [
                        'product_id' => $product->id,
                        'error' => $result->error,
                    ]);
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('WooCommerce product sync error', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('WooCommerce product sync completed', [
            'synced' => $count,
            'errors' => $errors,
            'total_processed' => $count + $errors,
        ]);
    }
}
