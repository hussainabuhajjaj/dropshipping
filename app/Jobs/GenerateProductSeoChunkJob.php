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
use Illuminate\Support\Facades\Log;

class GenerateProductSeoChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int[] */
    public array $productIds;

    public string $locale;
    public bool $force;

    public int $tries = 3;
    public ?int $timeout = 1200;

    /**
     * @param int[] $productIds
     */
    public function __construct(array $productIds, string $locale = 'en', bool $force = false)
    {
        $this->productIds = $productIds;
        $this->locale = $locale;
        $this->force = $force;
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\ReleaseCjClaim()];
    }

    public function handle(ProductSeoService $service): void
    {
        foreach ($this->productIds as $id) {
            try {
                $product = Product::find($id);
                if (! $product) {
                    continue;
                }

                $service->generate($product, $this->locale, $this->force);
            } catch (\Throwable $e) {
                Log::error('SEO chunk failed for product ' . $id, ['error' => $e->getMessage()]);
            }
        }
    }
}
