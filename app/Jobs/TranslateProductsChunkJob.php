<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\AI\ProductTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranslateProductsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int[] */
    public array $productIds;

    /** @var array<int,string> */
    public array $locales;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes for chunk processing
    public int $maxExceptions = 3;

    /**
     * @param int[] $productIds
     * @param array<int,string> $locales
     */
    public function __construct(array $productIds, array $locales)
    {
        $this->productIds = $productIds;
        $this->locales = $locales;
        $this->onConnection('redis');
        $this->onQueue('translations');
    }

    public function handle(ProductTranslationService $service): void
    {
        $startTime = microtime(true);
        $successCount = 0;
        $failureCount = 0;

        foreach ($this->productIds as $id) {
            try {
                $product = Product::find($id);
                if (! $product) {
                    Log::warning('Chunk translation: Product not found', ['product_id' => $id]);
                    $failureCount++;
                    continue;
                }

                // Check if already translated and not forcing
                if (! $this->shouldTranslate($product)) {
                    Log::info('Chunk translation: Product already translated, skipping', ['product_id' => $id]);
                    $successCount++;
                    continue;
                }

                $product->update(['translation_status' => 'in_progress']);
                
                $productStartTime = microtime(true);
                $service->translate($product, $this->locales, 'en', false);
                $productDuration = round(microtime(true) - $productStartTime, 2);
                
                $product->update([
                    'translation_status' => 'completed', 
                    'last_translation_at' => now(), 
                    'translated_locales' => $this->locales
                ]);

                Log::info('Chunk translation: Product completed', [
                    'product_id' => $id,
                    'duration_seconds' => $productDuration,
                    'locales' => $this->locales,
                ]);
                
                $successCount++;

            } catch (\Throwable $e) {
                $failureCount++;
                
                if (isset($product) && $product) {
                    $product->update(['translation_status' => 'failed']);
                }

                // Check if this is a transient failure for this specific product
                if ($this->isTransientFailure($e)) {
                    Log::warning('Chunk translation: Product failed transiently', [
                        'product_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other products instead of failing the whole chunk
                    continue;
                }
                
                Log::error('Chunk translation: Product failed permanently', [
                    'product_id' => $id,
                    'error' => $e->getMessage(),
                ]);
                
                // For non-transient failures, we still continue with other products
                // but we log the error for monitoring
            }
        }

        $totalDuration = round(microtime(true) - $startTime, 2);
        
        Log::info('Chunk translation completed', [
            'total_products' => count($this->productIds),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'duration_seconds' => $totalDuration,
            'locales' => $this->locales,
        ]);

        // Only fail the job if all products failed
        if ($failureCount === count($this->productIds)) {
            throw new \RuntimeException('All products in chunk failed translation');
        }
    }

    /**
     * Check if product should be translated
     */
    private function shouldTranslate(Product $product): bool
    {
        // Skip if already has translations for all locales
        foreach ($this->locales as $locale) {
            $translation = $product->translationForLocale($locale);
            if (! $translation || (! $translation->name && ! $translation->description)) {
                return true; // Need translation
            }
        }
        return false; // Already translated
    }

    /**
     * Determine if the exception is a transient failure
     */
    private function isTransientFailure(\Throwable $exception): bool
    {
        // Check for timeout exceptions
        if ($exception instanceof \Illuminate\Queue\TimeoutExceededException) {
            return true;
        }

        // Check for connection issues
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        // Check for HTTP client errors
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            $status = $exception->response?->status();
            if (in_array($status, [408, 425, 429, 500, 502, 503, 504], true)) {
                return true;
            }
        }

        // Check for timeout-related messages
        $message = strtolower($exception->getMessage());
        $timeoutKeywords = [
            'timed out',
            'timeout',
            'curl error 28',
            'connection timeout',
            'read timeout',
            'operation timed out',
        ];

        foreach ($timeoutKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
