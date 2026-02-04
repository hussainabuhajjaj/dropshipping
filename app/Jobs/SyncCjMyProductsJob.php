<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCjMyProductsJob implements ShouldQueue

{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected string $importedCountCacheKey = 'cj_my_products_imported_count';

    public int $pageNum;
    public int $pageSize;

    public function __construct(int $pageNum = 1, int $pageSize = 50)
    {
        $this->pageNum = $pageNum;
        $this->pageSize = $pageSize;
    }

    public function handle(): void
    {
        Log::info('CJ My Products Sync: handle start', [
            'page' => $this->pageNum,
            'pageSize' => $this->pageSize,
            'job' => static::class,
        ]);

        $client = app(CJDropshippingClient::class);
        $importer = app(CjProductImportService::class);

        $filters = [
            'pageNum' => $this->pageNum,
            'pageSize' => $this->pageSize,
        ];

        $endpoint = config('services.cj.my_products_endpoint', '/v1/product/myProduct/query');
        $method = strtolower((string) config('services.cj.my_products_method', 'get'));

        Log::info('CJ My Products Sync: request', [
            'page' => $this->pageNum,
            'pageSize' => $this->pageSize,
            'endpoint' => $endpoint,
            'method' => $method,
            'base_url' => config('services.cj.base_url'),
        ]);

        try {
            $resp = $client->listMyProducts($filters);
        } catch (ApiException $e) {
            Log::error('CJ My Products Sync: API error', [
                'page' => $this->pageNum,
                'pageSize' => $this->pageSize,
                'endpoint' => $endpoint,
                'method' => $method,
                'status' => $e->status,
                'code' => $e->codeString,
                'message' => $e->getMessage(),
                'body' => $e->body,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('CJ My Products Sync: unexpected error', [
                'page' => $this->pageNum,
                'pageSize' => $this->pageSize,
                'endpoint' => $endpoint,
                'method' => $method,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $data = is_array($resp->data) ? $resp->data : [];
        $products = (array) ($data['list'] ?? $data['data'] ?? $data['content'] ?? $data);
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
            'total' => $data['total'] ?? null,
            'response_status' => $resp->status,
            'response_message' => $resp->message,
        ]);
    }
}
