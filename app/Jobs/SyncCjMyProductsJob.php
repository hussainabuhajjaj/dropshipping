<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCjMyProductsJob implements ShouldQueue

    // Key for tracking imported count in cache (shared with UI)
    protected string $importedCountCacheKey = 'cj_my_products_imported_count';
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $pageNum;
    public int $pageSize;

    public function __construct(int $pageNum = 1, int $pageSize = 50)
    {
        $this->pageNum = $pageNum;
        $this->pageSize = $pageSize;
    }

    public function handle(): void
    {
        $client = app(CJDropshippingClient::class);
        $importer = app(CjProductImportService::class);

        $resp = $client->listMyProducts([
            'pageNum' => $this->pageNum,
            'pageSize' => $this->pageSize,
        ]);

        $products = (array) ($resp->data ?? []);
        $imported = 0;
        $skipped = 0;

        // Reset imported count at the start of the first job (page 1)
        if ($this->pageNum === 1) {
            \Illuminate\Support\Facades\Cache::put($this->importedCountCacheKey, 0, now()->addMinutes(60));
        }

        foreach ($products as $productData) {
            $pid = $productData['pid'] ?? null;
            if (!$pid) {
                continue;
            }
            // Skip if already imported
            if (Product::where('cj_pid', $pid)->exists()) {
                $skipped++;
                continue;
            }
            $imported++;
            $importer->importFromPayload($productData, null, [
                'respectSyncFlag' => false,
                'defaultSyncEnabled' => true,
                'shipToCountry' => config('services.cj.ship_to_default') ?? '',
            ]);

            // Increment imported count in cache for live UI update
            \Illuminate\Support\Facades\Cache::increment($this->importedCountCacheKey);
        }

        Log::info('CJ My Products Sync Job', [
            'page' => $this->pageNum,
            'pageSize' => $this->pageSize,
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }
}
