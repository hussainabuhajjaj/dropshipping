<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Products\Models\Product;
use App\Jobs\SyncCjVariantsJobImproved;
use App\Services\Notifications\NotificationBroadcastService;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function __construct(private readonly NotificationBroadcastService $notifications)
    {
    }

    public function created(Product $product): void
    {
        if ($product->cj_pid && $product->cj_sync_enabled) {
            Log::info('Product created; dispatching variant sync', [
                'product_id' => $product->id,
                'cj_pid' => $product->cj_pid,
            ]);
            SyncCjVariantsJobImproved::dispatch($product->cj_pid);
        }

        if ($product->is_active) {
            $this->notifications->broadcastNewProduct($product);
        }
    }

    public function updated(Product $product): void
    {
        $changed = $product->getChanges();

        if ((isset($changed['cj_pid']) || isset($changed['cj_sync_enabled'])) && $product->cj_pid && $product->cj_sync_enabled) {
            Log::info('Product updated; dispatching variant sync', [
                'product_id' => $product->id,
                'cj_pid' => $product->cj_pid,
                'changed_fields' => array_keys($changed),
            ]);
            SyncCjVariantsJobImproved::dispatch($product->cj_pid);
        }

        if (array_key_exists('is_active', $changed) && $product->is_active) {
            $this->notifications->broadcastNewProduct($product);
        }
    }
}
