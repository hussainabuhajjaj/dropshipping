<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\AI\ProductCompareAtService;
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
            logger()->error('Failed to generate compare-at prices', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
