<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Services\Api\ApiException;
use App\Services\CjPidClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncCjVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 5;
    public int $maxExceptions = 3;

    // Rate limiting: 6 QPS with burst capacity
    public int $rateLimit = 6;
    public int $burstCapacity = 12;

    public function __construct(
        public string $cjPid,
        public ?string $claimToken = null,
    ) {
        $this->onQueue('cj-sync');
    }

    public function handle(CJDropshippingClient $client, CjPidClaimService $claimService): void
    {
        $claimToken = $this->claimToken ?? $this->acquireClaim($claimService);
        
        try {
            $this->ensureRateLimit();
            
            $product = $this->getProduct();
            if (!$product) {
                Log::warning('Product not found for CJ sync', ['cj_pid' => $this->cjPid]);
                return;
            }

            $variants = $this->fetchVariants($client);
            if ($variants === null || $variants === []) {
                Log::info('No variants found for CJ product', ['cj_pid' => $this->cjPid]);
                return;
            }

            $this->syncVariants($product, $variants);
            $this->updateProductSyncStatus($product);

            Log::info('Successfully synced CJ variants', [
                'cj_pid' => $this->cjPid,
                'variant_count' => count($variants),
                'product_id' => $product->id,
            ]);

        } catch (Throwable $e) {
            $this->handleException($e);
        } finally {
            $this->releaseClaim($claimService, $claimToken);
        }
    }

    private function acquireClaim(CjPidClaimService $claimService): string
    {
        $token = $claimService->claim($this->cjPid, ttlSeconds: 1800);
        if (!$token) {
            Log::warning('Failed to acquire claim for CJ product', ['cj_pid' => $this->cjPid]);
            $this->release(30);
            throw new \RuntimeException('Unable to acquire processing claim');
        }
        return $token;
    }

    private function releaseClaim(CjPidClaimService $claimService, ?string $token): void
    {
        if ($token) {
            try {
                $claimService->release($this->cjPid, $token);
            } catch (Throwable $e) {
                Log::warning('Failed to release claim', [
                    'cj_pid' => $this->cjPid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function ensureRateLimit(): void
    {
        $key = "cj:rate_limit:variants";
        $now = now()->timestamp;
        $window = 1; // 1 second window for 6 QPS
        
        // Use Redis atomic operations for rate limiting
        $requests = Cache::store('redis')->add($key . ':count', 1, $window) ? 1 : 
                   Cache::store('redis')->increment($key . ':count');
        
        if ($requests > $this->rateLimit) {
            $ttl = Cache::store('redis')->get($key . ':ttl');
            if (!$ttl) {
                Cache::store('redis')->put($key . ':ttl', $now + $window, $window);
                $ttl = $now + $window;
            }
            
            $delay = max(0, $ttl - $now);
            if ($delay > 0) {
                Log::info('Rate limit exceeded, delaying job', [
                    'cj_pid' => $this->cjPid,
                    'delay' => $delay,
                    'requests' => $requests,
                ]);
                $this->release((int) $delay);
                throw new \RuntimeException('Rate limit exceeded');
            }
        }
    }

    private function getProduct(): ?Product
    {
        return Product::where('cj_pid', $this->cjPid)
            ->lockForUpdate()
            ->first();
    }

    private function fetchVariants(CJDropshippingClient $client): ?array
    {
        try {
            $resp = $client->getVariantsByPid($this->cjPid);
            return $this->extractVariants($resp->data ?? null);
        } catch (ApiException $e) {
            if ($this->isRemovedFromShelves($e)) {
                $this->markProductRemoved($e->getMessage());
                return null;
            }
            throw $e;
        }
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $variantIds = array_filter(array_column($variants, 'vid'));
        $existingVariants = ProductVariant::where('product_id', $product->id)
            ->whereIn('cj_vid', $variantIds)
            ->get()
            ->keyBy('cj_vid');

        DB::transaction(function () use ($product, $variants, $existingVariants) {
            $updates = [];
            $inserts = [];

            foreach ($variants as $variantData) {
                $vid = (string) ($variantData['vid'] ?? '');
                if ($vid === '') continue;

                $variant = $existingVariants->get($vid);
                
                if (!$variant) {
                    $variant = new ProductVariant();
                    $variant->product_id = $product->id;
                    $variant->cj_vid = $vid;
                    $variant->sku = $this->generateSku($variantData, $vid);
                    $inserts[] = $variant;
                }

                $variant->cj_variant_data = $variantData;
                $variant->cj_stock = (int) ($variantData['stock'] ?? 0);
                $variant->stock_on_hand = $variant->cj_stock;
                $variant->cj_stock_synced_at = now();
                $variant->price = $this->resolveVariantPrice($variantData, $variant, $product);
                $variant->title = $this->resolveVariantTitle($variantData, $variant, $vid);
                
                if ($variant->exists) {
                    $updates[] = $variant;
                }
            }

            // Batch operations for efficiency
            if (!empty($updates)) {
                foreach ($updates as $variant) {
                    $variant->save();
                }
            }

            if (!empty($inserts)) {
                ProductVariant::insert(array_map(function ($variant) {
                    return $variant->getAttributes();
                }, $inserts));
            }
        });
    }

    private function generateSku(array $variantData, string $vid): string
    {
        $variantSku = trim((string) ($variantData['variantSku'] ?? ''));
        return $variantSku !== '' ? $variantSku : 'CJ-' . $vid;
    }

    private function updateProductSyncStatus(Product $product): void
    {
        $product->update([
            'cj_removed_from_shelves_at' => null,
            'cj_removed_reason' => null,
            'cj_synced_at' => now(),
            'cj_stock_synced_at' => now(),
        ]);
    }

    private function handleException(Throwable $e): void
    {
        if ($e instanceof ApiException) {
            $this->handleApiException($e);
        } else {
            Log::error('Unexpected error in CJ variant sync', [
                'cj_pid' => $this->cjPid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($this->attempts() >= $this->tries) {
                $this->fail($e);
            } else {
                $delay = min(300, 30 * (2 ** ($this->attempts() - 1)));
                $this->release($delay);
            }
        }
    }

    private function handleApiException(ApiException $e): void
    {
        Log::warning('CJ API error in variant sync', [
            'cj_pid' => $this->cjPid,
            'error' => $e->getMessage(),
            'status' => $e->status,
            'code' => $e->codeString,
        ]);

        match ($e->status) {
            429 => $this->handleRateLimitError($e),
            404, 410 => $this->handleNotFoundError($e),
            500, 502, 503, 504 => $this->handleServerError($e),
            default => $this->handleGenericApiError($e),
        };
    }

    private function handleRateLimitError(ApiException $e): void
    {
        $attempt = $this->attempts();
        $baseDelay = 60;
        $maxDelay = 3600;
        
        // Parse Retry-After header if available
        $retryAfter = $this->parseRetryAfter($e);
        $delay = min($maxDelay, max($baseDelay, $retryAfter ?? ($baseDelay * (2 ** ($attempt - 1)))));
        
        Log::info('CJ rate limit hit, applying backoff', [
            'cj_pid' => $this->cjPid,
            'attempt' => $attempt,
            'delay' => $delay,
            'retry_after' => $retryAfter,
        ]);
        
        $this->release($delay);
    }

    private function handleNotFoundError(ApiException $e): void
    {
        if ($this->isRemovedFromShelves($e)) {
            $this->markProductRemoved($e->getMessage());
            return;
        }
        
        Log::warning('CJ product not found, marking as removed', [
            'cj_pid' => $this->cjPid,
            'error' => $e->getMessage(),
        ]);
        
        $this->markProductRemoved('Product not found: ' . $e->getMessage());
    }

    private function handleServerError(ApiException $e): void
    {
        $attempt = $this->attempts();
        if ($attempt >= $this->tries) {
            $this->fail($e);
            return;
        }
        
        $delay = min(600, 30 * (2 ** ($attempt - 1)));
        Log::info('CJ server error, retrying with backoff', [
            'cj_pid' => $this->cjPid,
            'attempt' => $attempt,
            'delay' => $delay,
        ]);
        
        $this->release($delay);
    }

    private function handleGenericApiError(ApiException $e): void
    {
        $attempt = $this->attempts();
        if ($attempt >= $this->tries) {
            $this->fail($e);
            return;
        }
        
        $delay = min(300, 15 * (2 ** ($attempt - 1)));
        Log::warning('CJ API error, retrying', [
            'cj_pid' => $this->cjPid,
            'attempt' => $attempt,
            'delay' => $delay,
        ]);
        
        $this->release($delay);
    }

    private function parseRetryAfter(ApiException $e): ?int
    {
        // Implementation would depend on how ApiException stores response headers
        // This is a placeholder for the actual implementation
        return null;
    }

    private function extractVariants(mixed $data): ?array
    {
        if (!is_array($data)) {
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
            if (!is_array($candidate)) {
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
        Product::where('cj_pid', $this->cjPid)->update([
            'status' => 'draft',
            'is_active' => false,
            'cj_sync_enabled' => false,
            'cj_synced_at' => now(),
            'cj_removed_from_shelves_at' => now(),
            'cj_removed_reason' => $reason ? substr($reason, 0, 500) : 'Removed from shelves',
        ]);

        Log::warning('CJ product marked as removed during variants sync', [
            'cj_pid' => $this->cjPid,
            'reason' => $reason,
        ]);
    }

    private function resolveVariantTitle(array $variantData, ProductVariant $variant, string $vid): string
    {
        $candidates = [
            $variantData['variantName'] ?? null,
            $variantData['variantNameEn'] ?? null,
            $variantData['variantKey'] ?? null,
            $variantData['variantSku'] ?? null,
            $variant->title ?? null,
            'Variant ' . $vid,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '' && strtolower($value) !== 'null') {
                return $value;
            }
        }

        return 'Variant';
    }

    private function resolveVariantPrice(array $variantData, ProductVariant $variant, Product $product): float
    {
        // Try variant-specific prices first
        $candidate = $variantData['variantSellPrice']
            ?? $variantData['variantSugSellPrice']
            ?? $variantData['variantPrice'];
        
        // If variant price is not available, calculate from variant cost
        if (!is_numeric($candidate)) {
            $variantCost = $variantData['variantSellPrice'] ?? $variant->cost_price ?? 0;
            if (is_numeric($variantCost) && $variantCost > 0) {
                // Calculate variant price based on its own cost, not the potentially corrupted product price
                $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                $candidate = $pricing->minSellingPrice((float) $variantCost);
            } else {
                $candidate = 0.0;
            }
        }
        
        // Final validation to prevent corruption
        if (!is_numeric($candidate) || $candidate < 0) {
            $candidate = 0.0;
        }
        
        // Additional corruption prevention
        $variantCost = $variant->cost_price ?? 0;
        if ($variantCost > 0 && $candidate > ($variantCost * 100)) { // >100x markup is corruption
            Log::warning('Excessive variant price detected in resolveVariantPrice (Improved)', [
                'cj_pid' => $product->cj_pid,
                'cj_vid' => $variant->cj_vid,
                'variant_cost' => $variantCost,
                'candidate_price' => $candidate
            ]);
            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
            $candidate = $pricing->minSellingPrice((float) $variantCost);
        }
        
        return (float) $candidate;
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\ReleaseCjClaim()];
    }
}
