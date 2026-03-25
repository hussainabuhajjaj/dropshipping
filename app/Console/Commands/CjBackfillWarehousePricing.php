<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CjBackfillWarehousePricing extends Command
{
    protected $signature = 'cj:backfill-warehouse-pricing
                            {--pid= : Only process one CJ PID}
                            {--limit=0 : Max number of products to process (0 = all)}
                            {--chunk=100 : Chunk size for DB reads}
                            {--all : Process all CJ products, not only missing warehouse/pricing data}
                            {--refresh-from-api : Force refresh by PID instead of using stored payload first}
                            {--dry-run : Show affected products without updating}';

    protected $description = 'Backfill local warehouse, pricing_meta, cost_price, and selling_price for already imported CJ products.';

    public function handle(CjProductImportService $importer): int
    {
        if (! PricingService::usesNewEngine()) {
            $this->warn('pricing.use_new_engine is disabled. Backfill is blocked to preserve legacy behavior.');
            return self::INVALID;
        }

        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(10, (int) $this->option('chunk'));
        $specificPid = trim((string) ($this->option('pid') ?? ''));
        $processAll = (bool) $this->option('all');
        $refreshFromApi = (bool) $this->option('refresh-from-api');
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()
            ->whereNotNull('cj_pid')
            ->where('cj_pid', '!=', '')
            ->orderBy('id');

        if ($specificPid !== '') {
            $query->where('cj_pid', $specificPid);
        }

        if (! $processAll) {
            $query->where(function ($q): void {
                $q->whereNull('local_warehouse_id')
                    ->orWhereNull('pricing_meta')
                    ->orWhere('cost_price', '<=', 0)
                    ->orWhere('selling_price', '<=', 0);
            });
        }

        $total = $query->count();

        if ($total === 0) {
            $this->warn('No CJ products matched the backfill criteria.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} CJ products for warehouse/pricing backfill...");

        $bar = $this->output->createProgressBar($total);
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $processed = 0;

        $query->chunkById($chunk, function ($products) use (
            $importer,
            $limit,
            $refreshFromApi,
            $dryRun,
            &$updated,
            &$skipped,
            &$errors,
            &$processed,
            $bar
        ): bool {
            foreach ($products as $product) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                try {
                    if ($dryRun) {
                        $this->line(sprintf(
                            'Would backfill PID %s (product #%d) warehouse=%s pricing_meta=%s cost=%s selling=%s',
                            $product->cj_pid,
                            $product->id,
                            $product->local_warehouse_id ?? 'null',
                            $product->pricing_meta ? 'present' : 'null',
                            $product->cost_price,
                            $product->selling_price,
                        ));
                        $updated++;
                    } else {
                        $result = null;

                        if (! $refreshFromApi && is_array($product->cj_last_payload) && $product->cj_last_payload !== []) {
                            $variants = data_get($product->attributes, 'cj_variants');
                            $result = $importer->importFromPayload(
                                $product->cj_last_payload,
                                is_array($variants) ? $variants : null,
                                [
                                    'updateExisting' => true,
                                    'respectSyncFlag' => false,
                                    'defaultSyncEnabled' => true,
                                    'respectLocks' => false,
                                    'syncVariants' => true,
                                    'syncImages' => false,
                                    'syncReviews' => false,
                                    'skipWhenFresh' => false,
                                ],
                            );
                        }

                        if (! $result) {
                            $result = $importer->importByPid((string) $product->cj_pid, [
                                'updateExisting' => true,
                                'respectSyncFlag' => false,
                                'defaultSyncEnabled' => true,
                                'respectLocks' => false,
                                'syncVariants' => true,
                                'syncImages' => false,
                                'syncReviews' => false,
                                'skipWhenFresh' => false,
                            ]);
                        }

                        $result ? $updated++ : $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn("PID {$product->cj_pid} failed: {$e->getMessage()}");
                    Log::error('CJ warehouse/pricing backfill failed', [
                        'product_id' => $product->id,
                        'cj_pid' => $product->cj_pid,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processed++;
                $bar->advance();
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Result', 'Count'], [
            ['Updated', $updated],
            ['Skipped', $skipped],
            ['Errors', $errors],
            ['Processed', $processed],
            ['Matched', $total],
        ]);

        return self::SUCCESS;
    }
}
