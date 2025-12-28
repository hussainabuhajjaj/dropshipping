<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\AI\ProductSeoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProductSeoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $productId,
        public string $locale = 'en',
        public bool $force = false
    ) {
    }

    public function handle(ProductSeoService $service): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        try {
            $service->generate($product, $this->locale, $this->force);
        } catch (\Throwable $e) {
            logger()->error('Failed to generate product SEO', ['product_id' => $this->productId, 'error' => $e->getMessage()]);
        }
    }
}
