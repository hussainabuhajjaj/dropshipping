<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\WooCommerce\Models\WooCommerceProductMap;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
use App\Infrastructure\WooCommerce\WooCommerceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWooInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        private readonly array $productIds = [],
    ) {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(WooCommerceClient $client): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        $query = WooCommerceProductMap::query()
            ->where('status', 'synced')
            ->whereNotNull('woocommerce_product_id');

        if ($this->productIds !== []) {
            $query->whereIn('product_id', $this->productIds);
        }

        $count = 0;
        $errors = 0;

        foreach ($query->cursor() as $map) {
            try {
                $product = $map->product;
                if (! $product) {
                    continue;
                }

                $stock = max(0, (int) ($product->stock_on_hand ?? 0));

                $client->updateStock(
                    $map->woocommerce_product_id,
                    $stock,
                    $map->woocommerce_variation_id,
                );

                $count++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('WooCommerce inventory sync failed', [
                    'product_id' => $map->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('WooCommerce inventory sync completed', [
            'updated' => $count,
            'errors' => $errors,
        ]);
    }
}
