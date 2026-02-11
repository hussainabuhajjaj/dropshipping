<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use Illuminate\Console\Command;

class CjFixProductDetails extends Command
{
    protected $signature = 'cj:fix-product-details
        {--limit=500}
        {--with-variants}
        {--with-media}
        {--with-reviews}
        {--without-reviews}
        {--review-score=}
        {--review-page-size=50}
        {--review-max-pages=10}
        {--pid=}
        {--pids=*}';

    protected $description = 'Reimport CJ products with missing/placeholder names/descriptions and update details.';

    public function handle(CjProductImportService $importService): int
    {
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : 500;
        $withVariants = (bool) $this->option('with-variants');
        $withMedia = (bool) $this->option('with-media');
        $withReviews = (bool) $this->option('with-reviews');
        if (! $withReviews) {
            $withReviews = ! (bool) $this->option('without-reviews');
        }
        $reviewScore = $this->option('review-score');
        $reviewScore = is_numeric($reviewScore) ? max(1, min(5, (int) $reviewScore)) : null;
        $reviewPageSize = (int) $this->option('review-page-size');
        $reviewPageSize = $reviewPageSize > 0 ? min($reviewPageSize, 100) : 50;
        $reviewMaxPages = (int) $this->option('review-max-pages');
        $reviewMaxPages = $reviewMaxPages > 0 ? $reviewMaxPages : 10;

        $pidOption = $this->option('pid');
        $pidList = (array) $this->option('pids');
        if ($pidOption) {
            $pidList[] = $pidOption;
        }
        $pidList = array_values(array_filter(array_unique($pidList)));

        if ($pidList !== []) {
            $products = Product::query()
                ->whereIn('cj_pid', $pidList)
                ->get();
            $this->info('Processing specific PIDs: ' . implode(', ', $pidList));
        } else {
            $products = Product::query()
                ->whereNotNull('cj_pid')
                ->where(function ($q) {
                    $q->whereNull('name')
                        ->orWhere('name', '')
                        ->orWhere('name', 'like', 'CJ Product%')
                        ->orWhereNull('description')
                        ->orWhere('description', '');
                })
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $this->info('Found ' . $products->count() . " products to fix (limit {$limit}).");
        }

        $processed = 0;
        $updated = 0;

        foreach ($products as $product) {
            /** @var Product $product */
            $pid = $product->cj_pid;
            if (! $pid) {
                continue;
            }

            $this->line("Reimporting {$pid} (product {$product->id})...");

            $updatedProduct = $importService->importByPid($pid, [
                'updateExisting' => true,
                'respectLocks' => false,
                'respectSyncFlag' => false,
                'defaultSyncEnabled' => true,
                'syncVariants' => $withVariants,
                'syncImages' => $withMedia,
                'syncReviews' => $withReviews,
                'reviewScore' => $reviewScore,
                'reviewPageSize' => $reviewPageSize,
                'reviewMaxPages' => $reviewMaxPages,
                'translate' => false,
                'generateSeo' => false,
            ]);

            if ($updatedProduct) {
                $updated++;
            }
            $processed++;
        }

        $this->info("Processed {$processed}; updated {$updated}.");

        return Command::SUCCESS;
    }
}
