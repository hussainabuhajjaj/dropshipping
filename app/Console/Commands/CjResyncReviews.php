<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use Illuminate\Console\Command;

class CjResyncReviews extends Command
{
    protected $signature = 'cj:resync-reviews
        {--limit=0 : Limit number of CJ products to process, 0 for all}
        {--chunk=100 : Number of products to process per chunk}
        {--delay=0 : Delay in seconds between chunks}
        {--review-score= : Optional CJ review score filter (1-5)}
        {--review-page-size=50 : Reviews fetched per request}
        {--review-max-pages=10 : Max review pages per product}
        {--pid= : Sync reviews for a single CJ product id}
        {--pids=* : Sync reviews for specific CJ product ids}';

    protected $description = 'Re-sync CJ product reviews for all or selected products.';

    public function handle(CjProductImportService $importService): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $delay = max(0, (int) $this->option('delay'));
        $reviewScore = $this->option('review-score');
        $reviewScore = is_numeric($reviewScore) ? max(1, min(5, (int) $reviewScore)) : null;
        $reviewPageSize = max(1, min(100, (int) $this->option('review-page-size')));
        $reviewMaxPages = max(1, (int) $this->option('review-max-pages'));

        $pidOption = $this->option('pid');
        $pidList = (array) $this->option('pids');
        if ($pidOption) {
            $pidList[] = $pidOption;
        }
        $pidList = array_values(array_filter(array_unique(array_map('strval', $pidList))));

        $query = Product::query()
            ->whereNotNull('cj_pid')
            ->where('cj_pid', '!=', '')
            ->orderBy('id');

        if ($pidList !== []) {
            $query->whereIn('cj_pid', $pidList);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No CJ products found for review sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing CJ reviews for {$total} product(s)...");

        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunkSize, function ($products) use (
            $importService,
            $reviewScore,
            $reviewPageSize,
            $reviewMaxPages,
            $delay,
            $bar,
            &$processed,
            &$created,
            &$updated,
            &$errors
        ): void {
            foreach ($products as $product) {
                try {
                    $result = $importService->syncReviews($product, [
                        'score' => $reviewScore,
                        'pageSize' => $reviewPageSize,
                        'maxPages' => $reviewMaxPages,
                        'throwOnFailure' => false,
                    ]);

                    $created += (int) ($result['created'] ?? 0);
                    $updated += (int) ($result['updated'] ?? 0);
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Review sync failed for {$product->cj_pid}: {$e->getMessage()}");
                }

                $processed++;
                $bar->advance();
            }

            if ($delay > 0) {
                sleep($delay);
            }
        }, 'id');

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Products processed', $processed],
                ['Reviews created', $created],
                ['Reviews updated', $updated],
                ['Errors', $errors],
            ]
        );

        return self::SUCCESS;
    }
}
