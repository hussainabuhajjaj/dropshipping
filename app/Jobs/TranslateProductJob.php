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

class TranslateProductJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?int $timeout = 2000;
    public int $tries = 3;

    /**
     * @param array<int, string> $locales
     */
    public function __construct(
        public int $productId,
        public array $locales,
        public string $sourceLocale = 'en',
        public bool $force = false
    ) {
    }

    public function handle(ProductTranslationService $service): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        try {
            $product->update(['translation_status' => 'in_progress']);
            
            $service->translate($product, $this->locales, $this->sourceLocale, $this->force);
            
            $product->update([
                'translation_status' => 'completed',
                'last_translation_at' => now(),
                'translated_locales' => $this->locales,
            ]);
        } catch (\Throwable $e) {
            $product->update(['translation_status' => 'failed']);
            logger()->error('Translation job failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
