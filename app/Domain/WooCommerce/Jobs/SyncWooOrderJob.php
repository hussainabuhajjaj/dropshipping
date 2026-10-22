<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\Orders\Models\Order;
use App\Domain\WooCommerce\Services\WooCommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWooOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        private readonly int $orderId,
    ) {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(WooCommerceOrderSyncService $orderSync): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $result = $orderSync->syncOrder($order);

        if (! $result->success && $this->attempts() < $this->tries) {
            $this->release(30 * $this->attempts());
        }
    }
}
