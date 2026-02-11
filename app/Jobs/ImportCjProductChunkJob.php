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
use Illuminate\Support\Facades\Cache;

class ImportCjProductChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, array> */
    public array $products;

    public int $tries = 3;
    public ?int $timeout = 1200;

    /**
     * @param array<int, array> $products
     */
    public function __construct(array $products)
    {
        $this->products = $products;
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\ReleaseCjClaim()];
    }

    public function handle(CjProductImportService $importService): void
    {
        $claimService = app(\App\Services\CjPidClaimService::class);

        // Use bulk import to upsert DB rows and dispatch downstream jobs
        try {
            $samplePid = $this->resolvePidFromPayload($this->products[0] ?? []);
            \Log::info('ImportCjProductChunkJob starting', ['count' => count($this->products), 'sample_pid' => $samplePid]);
            $result = $importService->importBulkFromPayloads($this->products, [
                'dispatchChunkSize' => 50,
                'translate' => true,
                'generateSeo' => true,
                'syncMedia' => true,
                'syncVariants' => true,
            ]);

            \Log::info('ImportCjProductChunkJob finished import', ['result' => $result]);

            // Release claim tokens based on original payloads to ensure we only
            // release our own claims.
            foreach ($this->products as $item) {
                $pid = $this->resolvePidFromPayload($item);
                $token = (string) ($item['_cj_claim_token'] ?? '');
                if ($pid === '' || $token === '') {
                    continue;
                }

                try {
                    $claimService->release($pid, $token);
                } catch (\Throwable $e) {
                    Log::warning('Failed to release claim for pid ' . $pid, ['error' => $e->getMessage()]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Chunk import failed', ['error' => $e->getMessage()]);

            // On failure, release our claims so these PIDs can be retried later
            foreach ($this->products as $item) {
                $pid = $this->resolvePidFromPayload($item);
                $token = (string) ($item['_cj_claim_token'] ?? '');
                if ($pid === '' || $token === '') {
                    continue;
                }

                try {
                    $claimService->release($pid, $token);
                } catch (\Throwable $e) {
                    Log::warning('Failed to release claim after error for pid ' . $pid, ['error' => $e->getMessage()]);
                }
            }

            $msg = strtolower($e->getMessage() ?? '');
            if (! str_contains($msg, 'removed from shelves') && ! str_contains($msg, 'off shelf')) {
                ImportCjProductChunkJob::dispatch($this->products)
                    ->delay(now()->addSeconds(30))
                    ->onQueue('import');
            }
        }
    }

    private function resolvePidFromPayload(array $item): string
    {
        return (string)($item['pid'] ?? $item['productId'] ?? $item['product_id'] ?? $item['id'] ?? '');
    }
}
