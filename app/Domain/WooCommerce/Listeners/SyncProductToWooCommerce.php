<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Listeners;

use App\Domain\Products\Models\Product;
use App\Domain\WooCommerce\Jobs\SyncWooProductJob;
use Illuminate\Support\Facades\Log;

class SyncProductToWooCommerce
{
    public function created(Product $product): void
    {
        $this->sync($product);
    }

    public function updated(Product $product): void
    {
        $this->sync($product);
    }

    public function deleted(Product $product): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        try {
            $map = \App\Domain\WooCommerce\Models\WooCommerceProductMap::query()
                ->where('product_id', $product->id)
                ->first();

            if ($map) {
                app(\App\Domain\WooCommerce\Services\WooCommerceProductSyncService::class)
                    ->deleteProduct($product);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to sync product deletion to WooCommerce', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sync(Product $product): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        try {
            SyncWooProductJob::dispatch($product->id);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch WooCommerce product sync', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
