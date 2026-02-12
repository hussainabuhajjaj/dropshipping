<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Products\Services\ProductActivationValidator;
use App\Jobs\GenerateProductCompareAtJob;
use App\Models\Product;
use App\Services\ProductMarginLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplyProductMarginChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    /**
     * @param array<int, int> $productIds
     */
    public function __construct(
        public array $productIds,
        public float $margin,
        public bool $applyVariants = true,
        public bool $useLowCostRule = true,
        public float $lowCostMin = 0.01,
        public float $lowCostMax = 1.0,
        public float $lowCostMargin = 300.0,
    ) {
        $this->onQueue((string) config('pricing.bulk_margin_queue', 'pricing'));
    }

    public function handle(ProductMarginLogger $logger, ProductActivationValidator $validator): void
    {
        $records = Product::query()
            ->whereIn('id', $this->productIds)
            ->with(['variants', 'images'])
            ->get();

        $updated = 0;
        $skipped = 0;
        $variantUpdated = 0;
        $variantSkipped = 0;
        $compareAtQueued = 0;
        $activationSkipped = 0;
        $activated = 0;
        $lowCostProductApplied = 0;
        $lowCostVariantApplied = 0;
        $logRows = [];
        $logsInserted = 0;
        $productErrors = 0;
        $variantErrors = 0;
        $productErrorSamples = [];
        $variantErrorSamples = [];

        foreach ($records as $record) {
            try {
                $productCost = $this->normalizeAmount($record->cost_price);
                if ($productCost === null || $productCost < 0) {
                    $skipped++;
                    continue;
                }

                $oldSelling = $record->selling_price;
                $oldStatus = $record->status;
                $oldActive = (bool) $record->is_active;

                $appliedMargin = $this->margin;
                if ($this->useLowCostRule && $productCost >= $this->lowCostMin && $productCost <= $this->lowCostMax) {
                    $appliedMargin = $this->lowCostMargin;
                    $lowCostProductApplied++;
                }

                $newSelling = round($productCost * (1 + $appliedMargin / 100), 2);
                $record->update([
                    'selling_price' => $newSelling,
                ]);
                $updated++;

                $logRows[] = $logger->prepareProductRow($record, [
                    'event' => 'margin_updated',
                    'source' => 'manual',
                    'old_selling_price' => $oldSelling,
                    'new_selling_price' => $newSelling,
                    'old_status' => $oldStatus,
                    'new_status' => $record->status,
                    'notes' => "Margin set to {$appliedMargin}%",
                    'skip_sales_count' => true,
                ]);

                if (count($logRows) >= 500) {
                    $logsInserted += $logger->insertMany($logRows);
                    $logRows = [];
                }

                $needsCompareAt = false;

                if ($this->applyVariants) {
                    foreach ($record->variants as $variant) {
                        try {
                            $variant->setRelation('product', $record);

                            $variantCost = $this->normalizeAmount($variant->cost_price);
                            if ($variantCost === null || $variantCost < 0) {
                                $variantSkipped++;
                                continue;
                            }

                            $variantMargin = $this->margin;
                            if ($this->useLowCostRule && $variantCost >= $this->lowCostMin && $variantCost <= $this->lowCostMax) {
                                $variantMargin = $this->lowCostMargin;
                                $lowCostVariantApplied++;
                            }

                            $oldVariantPrice = $variant->price;
                            $newVariantPrice = round($variantCost * (1 + $variantMargin / 100), 2);

                            $variant->update([
                                'price' => $newVariantPrice,
                            ]);
                            $variantUpdated++;

                            $logRows[] = $logger->prepareVariantRow($variant, [
                                'event' => 'variant_margin_updated',
                                'source' => 'manual',
                                'old_selling_price' => $oldVariantPrice,
                                'new_selling_price' => $newVariantPrice,
                                'notes' => "Margin set to {$variantMargin}% for variant",
                                'skip_sales_count' => true,
                            ]);

                            if (count($logRows) >= 500) {
                                $logsInserted += $logger->insertMany($logRows);
                                $logRows = [];
                            }

                            if (is_numeric($oldVariantPrice) && is_numeric($variant->compare_at_price)) {
                                $price = (float) $newVariantPrice;
                                $oldPrice = (float) $oldVariantPrice;
                                $compareAt = (float) $variant->compare_at_price;
                                if ($price > $oldPrice && $compareAt <= $price) {
                                    $needsCompareAt = true;
                                }
                            }
                        } catch (\Throwable $variantException) {
                            $variantErrors++;
                            if (count($variantErrorSamples) < 5) {
                                $variantErrorSamples[] = [
                                    'variant_id' => $variant->id ?? null,
                                    'product_id' => $record->id,
                                    'error' => $variantException->getMessage(),
                                ];
                            }
                        }
                    }
                }

                if ($needsCompareAt) {
                    GenerateProductCompareAtJob::dispatch((int) $record->id, false)
                        ->onQueue((string) config('pricing.compare_at_queue', config('pricing.bulk_margin_queue', 'pricing')));
                    $compareAtQueued++;
                }

                if (! $oldActive) {
                    $errors = $validator->errorsForActivation($record);
                    if ($errors === []) {
                        $record->update([
                            'is_active' => true,
                            'status' => 'active',
                        ]);
                        $activated++;

                        $logRows[] = $logger->prepareProductRow($record, [
                            'event' => 'activated',
                            'source' => 'manual',
                            'old_selling_price' => $oldSelling,
                            'new_selling_price' => $newSelling,
                            'old_status' => $oldStatus,
                            'new_status' => 'active',
                            'notes' => 'Product activated after margin adjustment',
                            'skip_sales_count' => true,
                        ]);

                        if (count($logRows) >= 500) {
                            $logsInserted += $logger->insertMany($logRows);
                            $logRows = [];
                        }
                    } else {
                        $activationSkipped++;
                    }
                }
            } catch (\Throwable $productException) {
                $productErrors++;
                if (count($productErrorSamples) < 5) {
                    $productErrorSamples[] = [
                        'product_id' => $record->id,
                        'error' => $productException->getMessage(),
                    ];
                }
            }
        }

        if ($logRows !== []) {
            $logsInserted += $logger->insertMany($logRows);
        }

        Log::info('ApplyProductMarginChunkJob finished', [
            'products_requested' => count($this->productIds),
            'products_processed' => $records->count(),
            'updated' => $updated,
            'skipped' => $skipped,
            'variant_updated' => $variantUpdated,
            'variant_skipped' => $variantSkipped,
            'compare_at_queued' => $compareAtQueued,
            'activated' => $activated,
            'activation_skipped' => $activationSkipped,
            'low_cost_product_applied' => $lowCostProductApplied,
            'low_cost_variant_applied' => $lowCostVariantApplied,
            'logs_inserted' => $logsInserted,
            'product_errors' => $productErrors,
            'variant_errors' => $variantErrors,
            'product_error_samples' => $productErrorSamples,
            'variant_error_samples' => $variantErrorSamples,
        ]);

        if ($productErrors > 0 || $variantErrors > 0) {
            Log::warning('ApplyProductMarginChunkJob completed with partial errors', [
                'products_requested' => count($this->productIds),
                'product_errors' => $productErrors,
                'variant_errors' => $variantErrors,
                'product_error_samples' => $productErrorSamples,
                'variant_error_samples' => $variantErrorSamples,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ApplyProductMarginChunkJob failed', [
            'products_requested' => count($this->productIds),
            'margin' => $this->margin,
            'apply_variants' => $this->applyVariants,
            'use_low_cost_rule' => $this->useLowCostRule,
            'low_cost_min' => $this->lowCostMin,
            'low_cost_max' => $this->lowCostMax,
            'low_cost_margin' => $this->lowCostMargin,
            'error' => $exception->getMessage(),
        ]);
    }

    private function normalizeAmount(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $number = (float) $value;

            return is_finite($number) ? $number : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', $normalized) ?? '';
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;

        return is_finite($number) ? $number : null;
    }
}
