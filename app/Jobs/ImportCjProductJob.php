<?php

namespace App\Jobs;

use App\Domain\Products\Services\CjProductImportService;
use App\Events\ProductImported;
use App\Domain\Products\Models\Product;
use App\Services\Cj\CjCatalogImportTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportCjProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $pid;
    public array $options;
    public ?string $trackingKey;

    /**
     * Create a new job instance.
     */
    public function __construct(string $pid, array $options = [], ?string $trackingKey = null)
    {
        $this->pid = $pid;
        $this->options = $options;
        $this->trackingKey = $trackingKey;
    }

    /**
     * Execute the job.
     */
    public function handle(CjProductImportService $importService, CjCatalogImportTracker $tracker): void
    {
        try {
            $product = $importService->importByPid($this->pid, $this->options);

            if ($this->trackingKey) {
                if ($product) {
                    $tracker->markSuccess($this->trackingKey, $this->pid);
                } else {
                    $tracker->markFailure($this->trackingKey, $this->pid);
                }
            }

            if ($product) {
                $totalProducts = Product::count();
                event(new ProductImported($totalProducts));
            }
        } catch (\Throwable $e) {
            if ($this->trackingKey) {
                $tracker->markFailure($this->trackingKey, $this->pid);
                Log::warning('Tracked CJ import failed', [
                    'pid' => $this->pid,
                    'tracking_key' => $this->trackingKey,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            throw $e;
        }
    }
}
