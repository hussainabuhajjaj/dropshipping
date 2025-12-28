<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Products\Models\Product;
use App\Jobs\SyncCjVariantsJob;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Auto-sync variants when a product with CJ PID is created
     */
    public function created(Product $product): void
    {
        if ($product->cj_pid && $product->cj_sync_enabled) {
            Log::info('Product created; dispatching variant sync', [
                'product_id' => $product->id,
                'cj_pid' => $product->cj_pid,
            ]);
            SyncCjVariantsJob::dispatch($product->cj_pid);
        }
    }

    /**
     * Auto-sync variants when CJ PID changes or sync is re-enabled
     */
    public function updated(Product $product): void
    {
        $changed = $product->getChanges();
        
        if ((isset($changed['cj_pid']) || isset($changed['cj_sync_enabled'])) && $product->cj_pid && $product->cj_sync_enabled) {
            Log::info('Product updated; dispatching variant sync', [
                'product_id' => $product->id,
                'cj_pid' => $product->cj_pid,
                'changed_fields' => array_keys($changed),
            ]);
            SyncCjVariantsJob::dispatch($product->cj_pid);
        }
    }
}
