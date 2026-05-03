<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\CjApiRateLimiterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FixStockZeroJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800; // 30 minutes

    public int $tries = 2;

    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $batchSize = 500,
        private int $hours = 24,
        private int $apiDelay = 1100 // Keep below CJ's effective 1 request/second throttle
    ) {
        $this->onQueue('cj-sync');
    }

    public function handle(CJDropshippingClient $client): void
    {
        Log::info('Starting FixStockZeroJob', [
            'batch_size' => $this->batchSize,
            'hours' => $this->hours,
            'api_delay' => $this->apiDelay,
        ]);

        // Get variants with stock=0
        $query = ProductVariant::query()
            ->whereNotNull('cj_vid')
            ->where('cj_vid', '!=', '')
            ->where('stock_on_hand', 0)
            ->where('cj_stock_synced_at', '>=', now()->subHours($this->hours))
            ->orderBy('cj_stock_synced_at', 'desc')
            ->limit($this->batchSize);

        $variants = $query->get(['id', 'cj_vid', 'cj_stock_synced_at']);

        if ($variants->isEmpty()) {
            Log::info('No stock=0 variants found to fix');
            return;
        }

        $rateLimiter = new CjApiRateLimiterService($client);
        $totalUpdated = 0;
        $totalErrors = 0;
        $totalSkipped = 0;

        foreach ($variants as $variant) {
            try {
                $result = $this->fixVariant($rateLimiter, $variant);
                
                if ($result['updated']) {
                    $totalUpdated++;
                } elseif ($result['skipped']) {
                    $totalSkipped++;
                } else {
                    $totalErrors++;
                }

                // Delay to avoid rate limiting
                if ($this->apiDelay > 0) {
                    usleep($this->apiDelay * 1000);
                }

            } catch (\Exception $e) {
                $totalErrors++;
                Log::error('Failed to fix variant', [
                    'cj_vid' => $variant->cj_vid,
                    'error' => $e->getMessage(),
                ]);

                // Don't fail the entire job for individual variant errors
                continue;
            }
        }

        Log::info('FixStockZeroJob completed', [
            'processed' => $variants->count(),
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
        ]);

        // If there are more variants to process, dispatch another job
        $remaining = ProductVariant::query()
            ->whereNotNull('cj_vid')
            ->where('cj_vid', '!=', '')
            ->where('stock_on_hand', 0)
            ->where('cj_stock_synced_at', '>=', now()->subHours($this->hours))
            ->count();

        if ($remaining > 0) {
            Log::info('Dispatching next FixStockZeroJob', ['remaining' => $remaining]);
            self::dispatch($this->batchSize, $this->hours, $this->apiDelay);
        }
    }

    private function fixVariant(CjApiRateLimiterService $rateLimiter, ProductVariant $variant): array
    {
        // Get stock from CJ API with circuit breaker protection
        $resp = $rateLimiter->executeApiCall('getStockByVid', [$variant->cj_vid]);
        $data = $resp->data ?? null;
        
        // Calculate stock
        $totalInventory = 0;
        $foundValidData = false;
        
        if (is_array($data) && isset($data[0])) {
            $row = $data[0];
            if (is_array($row)) {
                $val = $row['totalInventoryNum'] ?? null;
                if ($val === null) $val = $row['totalInventory'] ?? null;
                if ($val === null) $val = $row['storageNum'] ?? null;
                if ($val === null) $val = $row['inventory'] ?? null;
                
                if (is_numeric($val)) {
                    $totalInventory = (int) $val;
                    $foundValidData = true;
                }
            }
        }
        
        if ($totalInventory > 0 && $foundValidData) {
            // Update variant
            $updated = ProductVariant::query()
                ->where('cj_vid', $variant->cj_vid)
                ->update([
                    'cj_stock' => $totalInventory,
                    'stock_on_hand' => $totalInventory,
                    'cj_stock_synced_at' => now(),
                ]);
            
            Log::debug('Fixed stock zero variant', [
                'cj_vid' => $variant->cj_vid,
                'old_stock' => 0,
                'new_stock' => $totalInventory,
                'updated' => $updated,
            ]);

            return ['updated' => $updated > 0, 'skipped' => false];
        }
        
        return ['updated' => false, 'skipped' => true];
    }
}
