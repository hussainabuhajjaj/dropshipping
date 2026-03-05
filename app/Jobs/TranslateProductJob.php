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

class TranslateProductJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300; // Reduced from 2000s to 5 minutes
    public int $tries = 3;
    public int $maxExceptions = 3;

    /**
     * @param array<int, string> $locales
     */
    public function __construct(
        public int $productId,
        public array $locales,
        public string $sourceLocale = 'en',
        public bool $force = false
    ) {
        $this->onConnection('redis');
        $this->onQueue('translations');
    }

    public function handle(ProductTranslationService $service): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            Log::warning('Translation job: Product not found', ['product_id' => $this->productId]);
            return;
        }

        try {
            $product->update(['translation_status' => 'in_progress']);
            
            $startTime = microtime(true);
            $service->translate($product, $this->locales, $this->sourceLocale, $this->force);
            $duration = round(microtime(true) - $startTime, 2);
            
            $product->update([
                'translation_status' => 'completed',
                'last_translation_at' => now(),
                'translated_locales' => $this->locales,
            ]);

            Log::info('Product translation completed', [
                'product_id' => $this->productId,
                'locales' => $this->locales,
                'duration_seconds' => $duration,
                'source_locale' => $this->sourceLocale,
            ]);
            
        } catch (\Throwable $e) {
            $product->update(['translation_status' => 'failed']);
            
            // Check if this is a transient failure
            if ($this->isTransientFailure($e)) {
                $attempt = max(1, $this->attempts());
                $delay = min(60 * (2 ** ($attempt - 1)), 300); // Max 5 minutes

                Log::warning('Translation job failed transiently, retrying', [
                    'product_id' => $this->productId,
                    'attempt' => $attempt,
                    'delay' => $delay,
                    'error' => $e->getMessage(),
                ]);

                $this->release($delay);
                return;
            }
            
            Log::error('Translation job failed permanently', [
                'product_id' => $this->productId,
                'locales' => $this->locales,
                'error' => $e->getMessage(),
                'attempts' => $this->attempts(),
            ]);
            
            throw $e;
        }
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
