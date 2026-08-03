<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Jobs;

use App\Domain\WooCommerce\Models\WooCommerceSyncLog;
use App\Domain\WooCommerce\Services\WooCommerceOrderSyncService;
use App\Domain\WooCommerce\Services\WooCommerceProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryWooSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct()
    {
        $this->onQueue(config('woocommerce.queue', 'woocommerce'));
    }

    public function handle(
        WooCommerceProductSyncService $productSync,
        WooCommerceOrderSyncService $orderSync,
    ): void {
        if (! config('woocommerce.enabled', false)) {
            return;
        }

        $failedLogs = WooCommerceSyncLog::query()
            ->where('type', 'woocommerce')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->limit(50)
            ->get();

        $retried = 0;

        foreach ($failedLogs as $log) {
            try {
                match ($log->entity_type) {
                    'product' => SyncWooProductJob::dispatch($log->entity_id),
                    'order' => SyncWooOrderJob::dispatch($log->entity_id),
                    'customer' => SyncWooCustomerJob::dispatch($log->entity_id),
                    default => null,
                };

                $log->update(['status' => 'retrying']);
                $retried++;
            } catch (\Throwable $e) {
                Log::warning('Failed to retry WooCommerce sync', [
                    'log_id' => $log->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('WooCommerce retry sync completed', [
            'retried' => $retried,
            'total_failed' => $failedLogs->count(),
        ]);
    }
}
