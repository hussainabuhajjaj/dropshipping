<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Services\CjProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductVariantsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int[] */
    public array $productIds;

    public int $tries = 3;
    public ?int $timeout = 1200;

    /** @param int[] $productIds */
    public function __construct(array $productIds)
    {
        $this->productIds = $productIds;
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\ReleaseCjClaim()];
    }

    public function handle(CjProductImportService $importService): void
    {
        try {
            $importService->syncVariantsBulk($this->productIds);
        } catch (\Throwable $e) {
            Log::error('SyncProductVariantsChunkJob failed', ['error' => $e->getMessage()]);
            // re-dispatch with delay
            self::dispatch($this->productIds)->delay(now()->addSeconds(30))->onQueue('variants');
        }
    }
}
