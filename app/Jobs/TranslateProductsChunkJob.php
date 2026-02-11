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
    public ?int $timeout = 1200;

    /**
     * @param int[] $productIds
     * @param array<int,string> $locales
     */
    public function __construct(array $productIds, array $locales)
    {
        $this->productIds = $productIds;
        $this->locales = $locales;
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\ReleaseCjClaim()];
    }

    public function handle(ProductTranslationService $service): void
    {
        foreach ($this->productIds as $id) {
            try {
                $product = Product::find($id);
                if (! $product) {
                    continue;
                }

                $product->update(['translation_status' => 'in_progress']);
                $service->translate($product, $this->locales, 'en', false);
                $product->update(['translation_status' => 'completed', 'last_translation_at' => now(), 'translated_locales' => $this->locales]);
            } catch (\Throwable $e) {
                Log::error('Translation chunk failed for product ' . $id, ['error' => $e->getMessage()]);
                if (isset($product) && $product) {
                    $product->update(['translation_status' => 'failed']);
                }
            }
        }
    }
}
