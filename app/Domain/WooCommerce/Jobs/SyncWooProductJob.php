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

class SyncWooProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        private readonly int $productId,
    ) {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(WooCommerceProductSyncService $syncService): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $syncService->syncProduct($product);
    }
}
