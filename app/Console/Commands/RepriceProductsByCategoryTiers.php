<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Infrastructure\Fulfillment\Clients\CJ\CjAlertService;
use App\Jobs\GenerateProductCompareAtJob;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepriceProductsByCategoryTiers extends Command
{
    protected $signature = 'products:reprice-by-category-tiers
        {--chunk=500 : Chunk size}
        {--without-variants : Do not update variant prices}
        {--without-compare-at : Do not queue compare-at refresh}
        {--without-activation : Do not activate inactive products that pass validation}
        {--min-quality-score=60 : Minimum quality score required for activation}
        {--dry-run : Preview only, do not update records}';

    protected $description = 'Apply category-based margin tiers and optionally queue compare-at regeneration.';

    public function handle(): int
    {
        $tiers = $this->normalizeTiers(config('pricing.category_margin_tiers', []));
        if ($tiers === []) {
            $this->warn('No category margin tiers configured. Set PRICING_CATEGORY_MARGIN_TIERS.');
            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $applyVariants = ! (bool) $this->option('without-variants');
        $queueCompareAt = ! (bool) $this->option('without-compare-at');
        $activateIfValid = ! (bool) $this->option('without-activation');
        $minQualityScore = max(0, min(100, (int) $this->option('min-quality-score')));
        $dryRun = (bool) $this->option('dry-run');
        $activationValidator = app(ProductActivationValidator::class);

        $totalUpdatedProducts = 0;
        $totalUpdatedVariants = 0;
        $totalComparedQueued = 0;
        $totalActivated = 0;
        $totalActivationValidationSkipped = 0;
        $totalActivationQualitySkipped = 0;
        $totalScanned = 0;
        $tierSummary = [];

        foreach ($tiers as $tier) {
            $categoryIds = $tier['category_ids'];
            $marginPercent = $tier['margin_percent'];
            $factor = 1 + ($marginPercent / 100);
            $factorSql = number_format($factor, 6, '.', '');

            $query = Product::query()
                ->whereIn('category_id', $categoryIds)
                ->orderBy('id');

            $candidates = (clone $query)->count();
            $updatedProducts = 0;
            $updatedVariants = 0;
            $compareAtQueued = 0;
            $activated = 0;
            $activationValidationSkipped = 0;
            $activationQualitySkipped = 0;
            $scanned = 0;

            if ($candidates === 0) {
                $tierSummary[] = [
                    'categories' => implode(',', $categoryIds),
                    'margin' => "{$marginPercent}%",
                    'scanned' => 0,
                    'products' => 0,
                    'variants' => 0,
                    'compare_at' => 0,
                    'activated' => 0,
                    'activation_skipped' => 0,
                ];
                continue;
            }

            $query->chunkById($chunk, function ($rows) use (
                $dryRun,
                $applyVariants,
                $queueCompareAt,
                $activateIfValid,
                $minQualityScore,
                $factorSql,
                &$scanned,
                &$updatedProducts,
                &$updatedVariants,
                &$compareAtQueued,
                &$activated,
                &$activationValidationSkipped,
                &$activationQualitySkipped,
                $activationValidator
            ): void {
                $ids = $rows->pluck('id')->all();
                $scanned += count($ids);

                if ($dryRun) {
                    return;
                }

                $affectedIds = Product::query()
                    ->whereIn('id', $ids)
                    ->whereNotNull('cost_price')
                    ->pluck('id')
                    ->all();

                if ($affectedIds === []) {
                    return;
                }

                $updatedProducts += Product::query()
                    ->whereIn('id', $affectedIds)
                    ->update([
                        'selling_price' => DB::raw("ROUND(cost_price * {$factorSql}, 2)"),
                        'updated_at' => now(),
                    ]);

                if ($applyVariants) {
                    $updatedVariants += DB::table('product_variants')
                        ->whereIn('product_id', $affectedIds)
                        ->whereNotNull('cost_price')
                        ->update([
                            'price' => DB::raw("ROUND(cost_price * {$factorSql}, 2)"),
                            'updated_at' => now(),
                        ]);
                }

                if ($queueCompareAt) {
                    foreach ($affectedIds as $productId) {
                        GenerateProductCompareAtJob::dispatch((int) $productId, false);
                        $compareAtQueued++;
                    }
                }

                if ($activateIfValid) {
                    $productsToValidate = Product::query()
                        ->whereIn('id', $affectedIds)
                        ->with([
                            'images:id,product_id',
                            'variants:id,product_id,price,cost_price',
                        ])
                        ->withQualityScore()
                        ->get();

                    foreach ($productsToValidate as $product) {
                        if ($product->is_active) {
                            continue;
                        }

                        $qualityScore = is_numeric($product->quality_score ?? null)
                            ? (float) $product->quality_score
                            : 0.0;

                        if ($qualityScore < $minQualityScore) {
                            $activationQualitySkipped++;
                            continue;
                        }

                        $errors = $activationValidator->errorsForActivation($product);
                        if ($errors !== []) {
                            $activationValidationSkipped++;
                            continue;
                        }

                        $product->update([
                            'is_active' => true,
                            'status' => 'active',
                        ]);
                        $activated++;
                    }
                }
            }, 'id');

            $totalScanned += $scanned;
            $totalUpdatedProducts += $updatedProducts;
            $totalUpdatedVariants += $updatedVariants;
            $totalComparedQueued += $compareAtQueued;
            $totalActivated += $activated;
            $totalActivationValidationSkipped += $activationValidationSkipped;
            $totalActivationQualitySkipped += $activationQualitySkipped;

            $tierSummary[] = [
                'categories' => implode(',', $categoryIds),
                'margin' => "{$marginPercent}%",
                'scanned' => $scanned,
                'products' => $updatedProducts,
                'variants' => $updatedVariants,
                'compare_at' => $compareAtQueued,
                'activated' => $activated,
                'activation_skipped' => $activationValidationSkipped + $activationQualitySkipped,
            ];
        }

        $this->table(
            ['Categories', 'Margin', 'Scanned', 'Products Updated', 'Variants Updated', 'Compare-at Queued', 'Activated', 'Activation Skipped'],
            array_map(static fn (array $row): array => [
                $row['categories'],
                $row['margin'],
                (string) $row['scanned'],
                (string) $row['products'],
                (string) $row['variants'],
                (string) $row['compare_at'],
                (string) $row['activated'],
                (string) $row['activation_skipped'],
            ], $tierSummary)
        );

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Mode', $dryRun ? 'DRY RUN' : 'APPLIED'],
            ['Total scanned', (string) $totalScanned],
            ['Total products updated', (string) $totalUpdatedProducts],
            ['Total variants updated', (string) $totalUpdatedVariants],
            ['Total compare-at queued', (string) $totalComparedQueued],
            ['Total activated', (string) $totalActivated],
            ['Activation skipped (quality)', (string) $totalActivationQualitySkipped],
            ['Activation skipped (validation)', (string) $totalActivationValidationSkipped],
            ['Min quality score', (string) $minQualityScore],
        ]);

        if (! $dryRun) {
            CjAlertService::alert('Category tier repricing completed', [
                'scanned' => $totalScanned,
                'products_updated' => $totalUpdatedProducts,
                'variants_updated' => $totalUpdatedVariants,
                'compare_at_queued' => $totalComparedQueued,
                'activated' => $totalActivated,
                'activation_skipped_quality' => $totalActivationQualitySkipped,
                'activation_skipped_validation' => $totalActivationValidationSkipped,
                'min_quality_score' => $minQualityScore,
                'tiers' => $tierSummary,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * @param mixed $rawTiers
     * @return array<int, array{category_ids: array<int, int>, margin_percent: float}>
     */
    private function normalizeTiers(mixed $rawTiers): array
    {
        if (! is_array($rawTiers)) {
            return [];
        }

        $tiers = [];
        foreach ($rawTiers as $key => $value) {
            $categoryIds = [];
            $margin = null;

            if (is_array($value)) {
                $rawCategoryIds = $value['category_ids'] ?? null;
                if (! is_array($rawCategoryIds) && isset($value['category_id'])) {
                    $rawCategoryIds = [$value['category_id']];
                }
                if (is_array($rawCategoryIds)) {
                    $categoryIds = array_values(array_unique(array_filter(array_map(
                        static fn ($id) => is_numeric($id) ? (int) $id : null,
                        $rawCategoryIds
                    ))));
                }

                $margin = $value['margin_percent'] ?? $value['margin'] ?? null;
            } elseif (is_numeric($key) && is_numeric($value)) {
                $categoryIds = [(int) $key];
                $margin = $value;
            }

            if ($categoryIds === [] || ! is_numeric($margin)) {
                continue;
            }

            $tiers[] = [
                'category_ids' => $categoryIds,
                'margin_percent' => max(0, (float) $margin),
            ];
        }

        return $tiers;
    }
}
