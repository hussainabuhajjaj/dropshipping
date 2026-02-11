<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Jobs\GenerateProductCompareAtJob;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetProductMargin extends Command
{
    protected $signature = 'products:set-margin
        {--percent= : Target margin percentage (e.g. 35)}
        {--chunk=500 : Chunk size}
        {--category-id= : Optional category filter}
        {--product-id=* : Optional one or more product IDs}
        {--without-variants : Do not update variants}
        {--without-compare-at : Do not queue compare-at regeneration}
        {--dry-run : Preview only, do not update records}';

    protected $description = 'Set selling price margin for products (and variants) in chunks for large datasets.';

    public function handle(): int
    {
        $percent = $this->option('percent');
        if (! is_numeric($percent)) {
            $this->error('Option --percent is required and must be numeric.');
            return self::FAILURE;
        }

        $marginPercent = (float) $percent;
        if ($marginPercent < 0) {
            $this->error('Option --percent must be >= 0.');
            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $applyVariants = ! (bool) $this->option('without-variants');
        $queueCompareAt = ! (bool) $this->option('without-compare-at');
        $dryRun = (bool) $this->option('dry-run');

        $factor = 1 + ($marginPercent / 100);
        $factorSql = number_format($factor, 6, '.', '');

        $query = Product::query()->orderBy('id');

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
            $this->warn('No products matched the provided filters.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} product(s) at {$marginPercent}% margin...");
        if ($dryRun) {
            $this->warn('Dry run enabled: no records will be updated.');
        }

        $processed = 0;
        $updatedProducts = 0;
        $updatedVariants = 0;
        $queuedCompareAt = 0;
        $activatedProducts = 0;
        $activationSkipped = 0;
        $activationValidator = app(ProductActivationValidator::class);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunk, function ($products) use (
            $dryRun,
            $applyVariants,
            $queueCompareAt,
            $factorSql,
            &$processed,
            &$updatedProducts,
            &$updatedVariants,
            &$queuedCompareAt,
            &$activatedProducts,
            &$activationSkipped,
            $activationValidator,
            $bar
        ): void {
            $ids = $products->pluck('id')->all();
            $processed += count($ids);

            if ($dryRun) {
                $bar->advance(count($ids));
                return;
            }

            $productUpdateQuery = Product::query()
                ->whereIn('id', $ids)
                ->whereNotNull('cost_price');

            $affectedIds = $productUpdateQuery->pluck('id')->all();
            if ($affectedIds !== []) {
                $updatedProducts += Product::query()
                    ->whereIn('id', $affectedIds)
                    ->update([
                        'selling_price' => DB::raw("ROUND(cost_price * {$factorSql}, 2)"),
                    ]);
            }

            if ($applyVariants && $affectedIds !== []) {
                $variantTable = DB::table('product_variants')
                    ->whereIn('product_id', $affectedIds)
                    ->whereNotNull('cost_price');

                $updatedVariants += $variantTable->update([
                    'price' => DB::raw("ROUND(cost_price * {$factorSql}, 2)"),
                    'updated_at' => now(),
                ]);
            }

            if ($queueCompareAt && $affectedIds !== []) {
                foreach ($affectedIds as $productId) {
                    GenerateProductCompareAtJob::dispatch((int) $productId, false);
                    $queuedCompareAt++;
                }
            }

            if ($affectedIds !== []) {
                $productsToValidate = Product::query()
                    ->whereIn('id', $affectedIds)
                    ->with([
                        'images:id,product_id',
                        'variants:id,product_id,price,cost_price',
                    ])
                    ->get();

                foreach ($productsToValidate as $product) {
                    if ($product->is_active) {
                        continue;
                    }

                    $errors = $activationValidator->errorsForActivation($product);
                    if ($errors !== []) {
                        $activationSkipped++;
                        continue;
                    }

                    $product->update([
                        'is_active' => true,
                        'status' => 'active',
                    ]);
                    $activatedProducts++;
                }
            }

            $bar->advance(count($ids));
        }, 'id');

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Value'], [
            ['Processed', $processed],
            ['Updated products', $updatedProducts],
            ['Updated variants', $updatedVariants],
            ['Compare-at queued', $queuedCompareAt],
            ['Activated products', $activatedProducts],
            ['Activation skipped', $activationSkipped],
        ]);

        return self::SUCCESS;
    }
}
