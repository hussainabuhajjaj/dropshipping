<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\AI\ProductMarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProductMarketingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $productId,
        public string $locale = 'en',
        public bool $force = false,
        public string $tone = 'friendly',
    ) {
    }

    public function handle(ProductMarketingService $service): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        try {
            $service->generate($product, $this->locale, $this->force, $this->tone);
        } catch (\Throwable $e) {
            logger()->error('Failed to generate marketing content', ['product_id' => $this->productId, 'error' => $e->getMessage()]);
        }
    }
}
