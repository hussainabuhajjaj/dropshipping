<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReimportProductsAndVariants extends Command
{
    protected $signature = 'products:reimport-cj 
                            {--limit=0 : Max number of products to process (0 = all)} 
                            {--chunk=200 : Chunk size for DB reads} 
                            {--delay=0 : Seconds to sleep after each chunk} 
                            {--per-product-delay=0 : Seconds to sleep after each product} 
                            {--stale-hours=0 : Only process products with cj_synced_at older than this many hours (0 = no filter)}';

    protected $description = 'Re-import CJ products and variants (VPS-friendly, memory-efficient, logs errors).';

    public function handle(CjProductImportService $importer): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(10, (int) $this->option('chunk'));
        $delay = max(0, (int) $this->option('delay'));
        $perProductDelay = max(0, (int) $this->option('per-product-delay'));
        $staleHours = max(0, (int) $this->option('stale-hours'));

        $query = Product::query()
            ->whereNotNull('cj_pid')
            ->where('cj_pid', '!=', '')
            ->orderBy('id');

        if ($staleHours > 0) {
            $cutoff = now()->subHours($staleHours);
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('cj_synced_at')
                  ->orWhere('cj_synced_at', '<', $cutoff);
            });
        }

        $total = $query->count();
        if ($total === 0) {
            $this->warn('No products with CJ PID found.');
            return self::SUCCESS;
        }

        $this->info("Re-importing {$total} products (chunk {$chunk}, delay {$delay}s, per-product delay {$perProductDelay}s)...");

        $bar = $this->output->createProgressBar($total);

        $synced = 0;
        $skipped = 0;
        $errors = 0;
        $processed = 0;

        $stop = false;

        $query->chunkById($chunk, function ($products) use (&$synced, &$skipped, &$errors, &$processed, $limit, $importer, $bar, $perProductDelay, $delay, &$stop) {
            foreach ($products as $product) {
                if ($limit > 0 && $processed >= $limit) {
                    $stop = true;
                    return false; // stop chunking
                }

                try {
                    $result = $importer->importByPid($product->cj_pid, [
                        'respectSyncFlag' => false,
                        'defaultSyncEnabled' => true,
                        'respectLocks' => false,
                        'syncVariants' => true,
                        'syncReviews' => false,
                        'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                    ]);

                    $result ? $synced++ : $skipped++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn("PID {$product->cj_pid} (product {$product->id}) failed: {$e->getMessage()}");
                    Log::error("CJ reimport failed for PID {$product->cj_pid}", ['exception' => $e]);
                }

                $bar->advance();
                $processed++;

                if ($perProductDelay > 0) {
                    sleep($perProductDelay);
                }
            }

            if ($delay > 0) {
                sleep($delay);
            }

            return !$stop; // continue if not stopped by limit
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Result', 'Count'], [
            ['Synced', $synced],
            ['Skipped', $skipped],
            ['Errors', $errors],
            ['Total Processed', $processed],
            ['Total Found', $total],
        ]);

        $this->info('CJ Re-import finished.');

        return self::SUCCESS;
    }
}