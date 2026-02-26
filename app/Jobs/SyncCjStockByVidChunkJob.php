<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use App\Domain\Products\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class SyncCjStockByVidChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 3;

    /**
     * @param array<int, string> $vids
     */
    public function __construct(public array $vids)
    {
        $this->onQueue((string) config('cj.stock_queue', 'cj-sync'));
    }

    public function handle(CJDropshippingClient $client): void
    {
        $vids = array_values(array_unique(array_filter(array_map('strval', $this->vids))));
        if ($vids === []) {
            return;
        }

        foreach ($vids as $vid) {
            try {
                $resp = $client->getStockByVid($vid);
                $data = $resp->data ?? null;

                $totalInventory = $this->sumTotalInventory($data);
                if ($totalInventory === null) {
                    Log::warning('CJ stock sync: unable to parse inventory from response', [
                        'cj_vid' => $vid,
                        'data_type' => gettype($data),
                        'data_keys' => is_array($data) ? array_keys(array_slice($data, 0, 20, true)) : null,
                        'data_preview' => $this->preview($data),
                    ]);
                    $totalInventory = 0;
                }

                $updated = ProductVariant::query()
                    ->where('cj_vid', $vid)
                    ->update([
                        'cj_stock' => $totalInventory,
                        'stock_on_hand' => $totalInventory,
                        'cj_stock_synced_at' => now(),
                    ]);

                if ($updated === 0) {
                    Log::warning('CJ stock sync: local variant not found', ['cj_vid' => $vid]);
                } else {
                    Log::info('CJ stock sync: updated', [
                        'cj_vid' => $vid,
                        'total_inventory' => $totalInventory,
                        'updated_rows' => $updated,
                    ]);
                }
            } catch (ApiException $e) {
                Log::warning('CJ stock sync failed', [
                    'cj_vid' => $vid,
                    'status' => $e->status,
                    'code' => $e->codeString,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::error('CJ stock sync failed (unexpected)', [
                    'cj_vid' => $vid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function preview(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_slice($data, 0, 3, true);
        }

        if (is_string($data)) {
            return substr($data, 0, 500);
        }

        return $data;
    }

    private function sumTotalInventory(mixed $data): ?int
    {
        // CJ queryByVid can return an array of warehouse rows or a nested data shape.
        if (! is_array($data)) {
            return null;
        }

        // If we were given a keyed object, try common nesting keys.
        if (! array_is_list($data)) {
            foreach (['data', 'list', 'content', 'rows', 'warehouseList'] as $k) {
                $nested = $data[$k] ?? null;
                if (is_array($nested)) {
                    $data = $nested;
                    break;
                }
            }
        }

        $sum = 0;
        $foundAny = false;
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }

            $val = Arr::get($row, 'totalInventoryNum');
            if ($val === null) {
                $val = Arr::get($row, 'totalInventory');
            }
            if ($val === null) {
                $val = Arr::get($row, 'storageNum');
            }
            if ($val === null) {
                $val = Arr::get($row, 'inventory');
            }
            if ($val === null) {
                $val = Arr::get($row, 'quantity');
            }

            if (is_numeric($val)) {
                $sum += (int) $val;
                $foundAny = true;
            }
        }

        return $foundAny ? max(0, $sum) : null;
    }
}
