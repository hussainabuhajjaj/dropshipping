<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Models\Product;
use Illuminate\Console\Command;

class ActivateProductsByQuality extends Command
{
    protected $signature = 'products:activate-by-quality
        {--chunk=500 : Chunk size}
        {--min-quality-score=90 : Minimum quality score required for activation}
        {--category-id= : Optional category filter}
        {--product-id=* : Optional one or more product IDs}
        {--dry-run : Preview only, do not update records}';

    protected $description = 'Activate inactive products in bulk when they pass quality score and activation validation.';

    public function handle(ProductActivationValidator $activationValidator): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $minQualityScore = max(0, min(100, (int) $this->option('min-quality-score')));
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()
            ->where('is_active', false)
            ->orderBy('id');

        $categoryId = $this->option('category-id');
        if (is_numeric($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        $productIds = array_values(array_filter(array_unique(array_map(
            static fn ($id) => is_numeric($id) ? (int) $id : null,
            (array) $this->option('product-id')
        ))));
        if ($productIds !== []) {
            $query->whereIn('id', $productIds);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('No inactive products matched the provided filters.');
            return self::SUCCESS;
        }

        $this->info("Evaluating {$total} inactive product(s) for activation...");
        if ($dryRun) {
            $this->warn('Dry run enabled: no records will be updated.');
        }

        $processed = 0;
        $eligible = 0;
        $activated = 0;
        $skippedQuality = 0;
        $skippedValidation = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunk, function ($rows) use (
            $minQualityScore,
            $dryRun,
            &$processed,
            &$eligible,
            &$activated,
            &$skippedQuality,
            &$skippedValidation,
            $activationValidator,
            $bar
        ): void {
            $ids = $rows->pluck('id')->all();

            $products = Product::query()
                ->whereIn('id', $ids)
                ->with([
                    'images:id,product_id',
                    'variants:id,product_id,price,cost_price',
                ])
                ->withQualityScore()
                ->get();

            foreach ($products as $product) {
                $processed++;

                $qualityScore = is_numeric($product->quality_score ?? null)
                    ? (float) $product->quality_score
                    : 0.0;

                if ($qualityScore < $minQualityScore) {
                    $skippedQuality++;
                    continue;
                }

                $errors = $activationValidator->errorsForActivation($product);
                if ($errors !== []) {
                    $skippedValidation++;
                    continue;
                }

                $eligible++;

                if ($dryRun) {
                    continue;
                }

                $product->update([
                    'is_active' => true,
                    'status' => 'active',
                ]);
                $activated++;
            }

            $bar->advance(count($ids));
        }, 'id');

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Value'], [
            ['Total matched inactive products', (string) $total],
            ['Processed', (string) $processed],
            ['Eligible for activation', (string) $eligible],
            ['Activated', (string) $activated],
            ['Skipped (quality score)', (string) $skippedQuality],
            ['Skipped (activation validation)', (string) $skippedValidation],
            ['Min quality score', (string) $minQualityScore],
            ['Mode', $dryRun ? 'DRY RUN' : 'APPLIED'],
        ]);

        return self::SUCCESS;
    }
}

