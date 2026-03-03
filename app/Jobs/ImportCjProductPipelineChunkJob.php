<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Services\CjProductImportService;
use App\Services\Cj\CjCatalogImportTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportCjProductPipelineChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<string> */
    public array $pids;

    /** @var array<string, mixed> */
    public array $options;

    public int $tries = 3;
    public ?int $timeout = 600; // 10 minutes per chunk

    /**
     * @param array<string> $pids
     * @param array<string, mixed> $options
     */
    public function __construct(array $pids, array $options)
    {
        $this->pids = $pids;
        $this->options = $options;
    }

    public function handle(CjProductImportService $importService): void
    {
        $trackingKey = $this->options['tracking_key'] ?? null;
        $chunkIndex = $this->options['chunk_index'] ?? 0;
        
        if (!$trackingKey) {
            Log::error('ImportCjProductPipelineChunkJob: No tracking key provided');
            return;
        }

        $tracker = app(CjCatalogImportTracker::class);
        
        try {
            Log::info('Processing CJ import chunk', [
                'tracking_key' => $trackingKey,
                'chunk_index' => $chunkIndex,
                'pids_count' => count($this->pids),
                'pids_sample' => array_slice($this->pids, 0, 3),
            ]);

            // PERFORMANCE OPTIMIZATION: Use optimized import settings
            $result = $importService->importBulkWithPipeline([
                'pids' => $this->pids,
                'margin_percent' => $this->options['margin_percent'] ?? 60,
                'enrich' => $this->options['enrich'] ?? true,
                'force_activate' => $this->options['force_activate'] ?? true,
                'skip_translations' => $this->options['skip_translations'] ?? false,
                'skip_seo' => $this->options['skip_seo'] ?? false,
                'chunk_size' => min(count($this->pids), 25), // Optimize chunk size
                'enrich_sleep_ms' => 100, // Reduce sleep delay
                'dry_run' => false,
            ]);

            // Apply default category if specified
            if (!empty($this->options['default_category_id'])) {
                DB::table('products')
                    ->whereIn('cj_pid', $this->pids)
                    ->whereNull('category_id')
                    ->update(['category_id' => $this->options['default_category_id']]);
            }

            // Update tracking progress
            $current = $tracker->get($trackingKey) ?: [];
            $tracker->set($trackingKey, array_merge($current, [
                'status' => 'processing',
                'processed' => ($current['processed'] ?? 0) + count($this->pids),
                'success' => ($current['success'] ?? 0) + ($result['imported'] ?? 0),
                'failed' => ($current['failed'] ?? 0) + (($result['fetched'] ?? 0) - ($result['imported'] ?? 0)),
                'chunks_completed' => ($current['chunks_completed'] ?? 0) + 1,
                'last_chunk_result' => $result,
                'updated_at' => now()->toISOString(),
            ]));

            // Check if all chunks are completed
            if (($current['chunks_completed'] ?? 0) + 1 >= ($current['chunks_total'] ?? 1)) {
                $final = $tracker->get($trackingKey);
                $tracker->set($trackingKey, array_merge($final, [
                    'status' => 'completed',
                    'completed_at' => now()->toISOString(),
                ]));
            }

            Log::info('CJ import chunk completed successfully', [
                'tracking_key' => $trackingKey,
                'chunk_index' => $chunkIndex,
                'result' => $result,
            ]);

        } catch (\Throwable $e) {
            Log::error('CJ import chunk failed', [
                'tracking_key' => $trackingKey,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update tracking with failure
            $current = $tracker->get($trackingKey) ?: [];
            $tracker->set($trackingKey, array_merge($current, [
                'status' => 'failed',
                'failed' => ($current['failed'] ?? 0) + count($this->pids),
                'error' => $e->getMessage(),
                'updated_at' => now()->toISOString(),
            ]));

            // Retry logic for transient failures
            if ($this->attempts() < $this->tries && $this->shouldRetry($e)) {
                $this->release(30 * $this->attempts()); // Exponential backoff
            }
        }
    }

    private function shouldRetry(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage() ?? '');
        
        // Don't retry for permanent failures
        $permanentFailures = [
            'removed from shelves',
            'off shelf',
            'not found',
            'unauthorized',
            'forbidden',
        ];

        foreach ($permanentFailures as $failure) {
            if (str_contains($message, $failure)) {
                return false;
            }
        }

        return true;
    }

    public function failed(\Throwable $exception): void
    {
        $trackingKey = $this->options['tracking_key'] ?? null;
        if ($trackingKey) {
            $tracker = app(CjCatalogImportTracker::class);
            $current = $tracker->get($trackingKey) ?: [];
            $tracker->set($trackingKey, array_merge($current, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString(),
            ]));
        }

        Log::error('CJ import chunk job failed permanently', [
            'tracking_key' => $trackingKey,
            'chunk_index' => $this->options['chunk_index'] ?? 0,
            'error' => $exception->getMessage(),
        ]);
    }
}
