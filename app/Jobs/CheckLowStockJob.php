<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\ProductVariant;
use App\Notifications\LowStockNotification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckLowStockJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Batchable;

    public int $timeout = 120;

    public function handle(): void
    {
        $recipient = config('mail.from.address');
        $variants = ProductVariant::query()
            ->whereNotNull('stock_on_hand')
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
            ->limit(100)
            ->get(['id', 'product_id', 'sku', 'title', 'stock_on_hand', 'low_stock_threshold']);

        if ($variants->isEmpty()) {
            return;
        }

        $payload = $variants->map(function (ProductVariant $variant) {
            return [
                'id' => $variant->id,
                'product' => $variant->product_id,
                'sku' => $variant->sku,
                'title' => $variant->title,
                'stock' => (int) $variant->stock_on_hand,
                'threshold' => (int) $variant->low_stock_threshold,
            ];
        })->values()->all();

        Log::warning('Low stock variants detected', ['variants' => $payload]);

        if ($recipient) {
            Notification::route('mail', $recipient)->notify(new LowStockNotification($payload));
        }
    }
}
