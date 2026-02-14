<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCjVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public function __construct(
        public string $cjPid,
    ) {
    }

    public function handle(CJDropshippingClient $client): void
    {
        try {
            // Find the product by CJ PID
            $product = Product::where('cj_pid', $this->cjPid)->first();
            
            if (!$product) {
                Log::warning('Product not found for CJ sync', ['cj_pid' => $this->cjPid]);
                return;
            }

            // Get variants from CJ API
            $resp = $client->getVariantsByPid($this->cjPid);

            $variants = $this->extractVariants($resp->data ?? null);
            if ($variants === null) {
                $data = $resp->data ?? null;
                Log::warning('No variants found in CJ response', [
                    'cj_pid' => $this->cjPid,
                    'data_type' => gettype($data),
                    'data_keys' => is_array($data) ? array_keys(array_slice($data, 0, 20, true)) : null,
                ]);
                return;
            }

            if ($variants === []) {
                Log::info('CJ response returned empty variants list', ['cj_pid' => $this->cjPid]);
                return;
            }

            foreach ($variants as $variantData) {
                $vid = (string) ($variantData['vid'] ?? '');
                
                if ($vid === '') {
                    continue;
                }

                // Find or create the variant
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('cj_vid', $vid)
                    ->first();

                if (!$variant) {
                    // Create new variant
                    $variant = new ProductVariant();
                    $variant->product_id = $product->id;
                    $variant->cj_vid = $vid;
                    $variant->sku = $variantData['variantSku'] ?? 'CJ-' . $vid;
                }

                // Update variant data
                $variant->cj_variant_data = $variantData;
                $variant->cj_stock = (int) ($variantData['stock'] ?? 0);
                $variant->stock_on_hand = $variant->cj_stock; // mirror CJ stock into local stock
                $variant->cj_stock_synced_at = now();

                // Update pricing if available
                $variantPrice = $variantData['variantPrice'] ?? $variantData['variantSellPrice'] ?? null;
                if (is_numeric($variantPrice)) {
                    $variant->price = (float) $variantPrice;
                }

                // Update variant name/title
                if (isset($variantData['variantName'])) {
                    $variant->title = $variantData['variantName'];
                }

                $variant->save();

                Log::info('Synced CJ variant', [
                    'product_id' => $product->id,
                    'cj_vid' => $vid,
                    'stock' => $variant->cj_stock,
                ]);
            }

            // Update product sync timestamp
            $product->cj_removed_from_shelves_at = null;
            $product->cj_removed_reason = null;
            $product->cj_synced_at = now();
            $product->save();

        } catch (ApiException $e) {
            if ($this->isRemovedFromShelves($e)) {
                $this->markProductRemoved($e->getMessage());
                return;
            }

            Log::warning('CJ variant sync failed', [
                'cj_pid' => $this->cjPid,
                'error' => $e->getMessage(),
                'status' => $e->status,
            ]);

            // If rate-limited by CJ, requeue with exponential backoff
            if ($e->status === 429) {
                $attempt = max(1, $this->attempts());
                $delay = min(60 * (2 ** ($attempt - 1)), 3600); // cap at 1 hour
                Log::info('CJ rate limit hit; releasing job back to queue', [
                    'cj_pid' => $this->cjPid,
                    'delay' => $delay,
                ]);
                $this->release($delay);
                return;
            }

            // For other errors, fail the job
            $this->fail($e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function extractVariants(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        if (array_is_list($data)) {
            return $data;
        }

        $candidates = [
            $data['variants'] ?? null,
            $data['list'] ?? null,
            $data['data'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if (array_is_list($candidate)) {
                return $candidate;
            }

            if (isset($candidate['variants']) && is_array($candidate['variants'])) {
                return $candidate['variants'];
            }

            if (isset($candidate['list']) && is_array($candidate['list'])) {
                return $candidate['list'];
            }
        }

        return null;
    }

    private function isRemovedFromShelves(ApiException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'removed from shelves')
            || str_contains($message, 'off shelf')
            || str_contains($message, 'offline')
            || in_array($e->codeString, ['PRODUCT_OFF_SHELF', '404'], true);
    }

    private function markProductRemoved(?string $reason = null): void
    {
        Product::query()
            ->where('cj_pid', $this->cjPid)
            ->update([
                'status' => 'draft',
                'is_active' => false,
                'cj_sync_enabled' => false,
                'cj_synced_at' => now(),
                'cj_removed_from_shelves_at' => now(),
                'cj_removed_reason' => $reason !== null ? substr($reason, 0, 500) : 'Removed from shelves',
            ]);

        Log::warning('CJ product marked as removed during variants sync', [
            'cj_pid' => $this->cjPid,
            'reason' => $reason,
        ]);
    }
}
