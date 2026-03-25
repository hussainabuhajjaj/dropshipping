<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\PricingService;
use App\Models\Product;
use Illuminate\Console\Command;

class CjFixZeroPrices extends Command
{
    protected $signature = 'cj:fix-zero-prices 
                            {--limit=50 : Number of products to fix per run}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Fix CJ products with zero/null prices by re-importing with enrichment';

    public function handle(): int
    {
        if (PricingService::usesNewEngine()) {
            $this->warn('pricing.use_new_engine is enabled. This legacy zero-price repair command is blocked to avoid mixing pricing engines.');

            return self::INVALID;
        }

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Finding CJ products with zero/null prices...');

        $products = Product::whereNotNull('cj_pid')
            ->where(function ($q) {
                $q->where('cost_price', '<=', 0)
                  ->orWhereNull('cost_price');
            })
            ->limit($limit)
            ->get(['id', 'name', 'cj_pid', 'cost_price', 'selling_price']);

        if ($products->isEmpty()) {
            $this->info('✅ No products with zero prices found!');
            return self::SUCCESS;
        }

        $this->warn("Found {$products->count()} products with zero prices");

        if ($dryRun) {
            $this->table(
                ['ID', 'Name', 'PID', 'Cost', 'Selling'],
                $products->map(fn ($p) => [
                    $p->id,
                    substr($p->name, 0, 40),
                    $p->cj_pid,
                    $p->cost_price ?? 'NULL',
                    $p->selling_price ?? 'NULL',
                ])
            );
            $this->info('Dry run - no changes made');
            return self::SUCCESS;
        }

        $pids = $products->pluck('cj_pid')->toArray();

        $this->info('Re-importing with pipeline...');
        $bar = $this->output->createProgressBar(count($pids));

        $service = app(CjProductImportService::class);
        
        try {
            $result = $service->importBulkWithPipeline([
                'pids' => $pids,
                'margin_percent' => (float) config('services.cj.import_margin', 60),
                'enrich' => true,
            ]);

            $bar->finish();
            $this->newLine(2);

            $this->info('✅ Re-import completed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Fetched', $result['fetched']],
                    ['Enriched', $result['enriched']],
                    ['Imported', $result['imported']],
                    ['Priced', $result['priced']],
                    ['Activated', $result['activated']],
                    ['Failed Activation', $result['failed_activation']],
                ]
            );

            if ($result['failed_activation'] > 0) {
                $this->warn('Some products failed activation:');
                foreach ($result['activation_errors'] as $pid => $errors) {
                    $this->line("  {$pid}: " . implode(', ', $errors));
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
