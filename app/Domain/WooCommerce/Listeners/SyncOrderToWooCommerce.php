<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Listeners;

use App\Domain\WooCommerce\Jobs\SyncWooOrderJob;
use App\Events\Orders\OrderPaid;
use Illuminate\Support\Facades\Log;

class SyncOrderToWooCommerce
{
    public function handle(OrderPaid $event): void
    {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        try {
            SyncWooOrderJob::dispatch($event->order->id);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch WooCommerce order sync', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
