<?php

namespace App\Jobs;

use App\Domain\Products\Services\CjProductImportService;
use App\Events\ProductImported;
use App\Domain\Products\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportCjProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $pid;
    public array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(string $pid, array $options = [])
    {
        $this->pid = $pid;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(CjProductImportService $importService): void
    {
        $importService->importByPid($this->pid, $this->options);
        // Broadcast the current product count after import
        $totalProducts = Product::count();
        event(new ProductImported($totalProducts));
    }
}
