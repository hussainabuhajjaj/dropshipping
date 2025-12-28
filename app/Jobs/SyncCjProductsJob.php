<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\CjProductSnapshot;
use App\Services\Api\ApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCjProductsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public int $pageNum = 1,
        public int $pageSize = 24,
    ) {
    }

    public function handle(CJDropshippingClient $client): void
    {
        try {
            $resp = $client->listProductsV2([
                'pageNum' => $this->pageNum,
                'pageSize' => $this->pageSize,
            ]);
        } catch (ApiException $e) {
            Log::warning('CJ sync failed', ['page' => $this->pageNum, 'error' => $e->getMessage(), 'status' => $e->status]);

            // If rate-limited by CJ, requeue with exponential backoff
            if ($e->status === 429) {
                $attempt = max(1, $this->attempts());
                $delay = min(60 * (2 ** ($attempt - 1)), 3600); // cap at 1 hour
                Log::info('CJ rate limit hit; releasing job back to queue', ['page' => $this->pageNum, 'delay' => $delay]);
                $this->release($delay);
                return;
            }

            return;
        }

        $content = $resp->data['content'][0]['productList'] ?? [];

        foreach ($content as $item) {
            $pid = (string) ($item['id'] ?? '');
            if ($pid === '') {
                continue;
            }

            CjProductSnapshot::updateOrCreate(
                ['pid' => $pid],
                [
                    'name' => $item['nameEn'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'category_id' => $item['categoryId'] ?? null,
                    'payload' => $item,
                    'synced_at' => now(),
                ]
            );
        }
    }
}
