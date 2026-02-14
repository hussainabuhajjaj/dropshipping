<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\AI\ProductCompareAtService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProductCompareAtJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $productId,
        public bool $force = false
    ) {
    }

    public function handle(ProductCompareAtService $service): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        try {
            $service->generate($product, $this->force);
        } catch (\Throwable $e) {
            if ($this->isTransientFailure($e)) {
                $attempt = max(1, $this->attempts());
                $delay = min(60 * (2 ** ($attempt - 1)), 1800);

                logger()->warning('Temporary compare-at generation failure; releasing job', [
                    'product_id' => $this->productId,
                    'attempt' => $attempt,
                    'delay' => $delay,
                    'error' => $e->getMessage(),
                ]);

                $this->release($delay);
                return;
            }

            logger()->error('Failed to generate compare-at prices', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isTransientFailure(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response?->status();
            if (in_array($status, [408, 425, 429, 500, 502, 503, 504], true)) {
                return true;
            }
        }

        $message = strtolower($exception->getMessage());
        foreach ([
            'timed out',
            'timeout',
            'curl error 28',
            'could not resolve host',
            'connection refused',
            'temporarily unavailable',
            'too many requests',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return $exception->getPrevious() instanceof \Throwable
            ? $this->isTransientFailure($exception->getPrevious())
            : false;
    }
}
