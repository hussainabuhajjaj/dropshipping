<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjCategoryResolver;
use App\Models\CjProductSnapshot;
use App\Models\Product;
use Illuminate\Console\Command;

class CjBackfillProductCategories extends Command
{
    protected $signature = 'cj:backfill-product-categories
        {--limit=5000 : Maximum number of products to scan}
        {--dry-run : Preview changes only}
        {--without-create : Do not create placeholder categories when missing}';

    protected $description = 'Backfill missing product.category_id using CJ payload category IDs.';

    public function handle(CjCategoryResolver $resolver): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $createMissing = ! (bool) $this->option('without-create');

        $query = Product::query()
            ->whereNull('category_id')
            ->whereNotNull('cj_pid')
            ->orderBy('id');

        $products = $query->limit($limit)->get();
        $snapshotsByPid = CjProductSnapshot::query()
            ->whereIn('pid', $products->pluck('cj_pid')->filter()->all())
            ->get()
            ->keyBy('pid');

        if ($products->isEmpty()) {
            $this->info('No products with missing category_id were found.');
            return self::SUCCESS;
        }

        $scanned = 0;
        $assigned = 0;
        $createdPlaceholders = 0;
        $wouldCreate = 0;
        $noPayload = 0;
        $unresolved = 0;
        $resolvedFromSnapshots = 0;

        foreach ($products as $product) {
            $scanned++;

            $payload = $this->extractPayload($product);
            $snapshotPayload = $this->extractSnapshotPayload(
                $snapshotsByPid->get((string) $product->cj_pid)
            );

            if ($payload === null && $snapshotPayload === null) {
                $noPayload++;
                continue;
            }

            $category = null;
            $hadExistingCategory = false;

            if ($payload !== null) {
                $candidateIds = $resolver->extractCandidateIds($payload);
                if ($candidateIds !== []) {
                    $hadExistingCategory = $this->categoryExistsForAnyCjId($candidateIds);
                    $category = $resolver->resolveFromPayload($payload, $createMissing && ! $dryRun);
                }
            }

            if (! $category && $snapshotPayload !== null) {
                $snapshotCandidateIds = $resolver->extractCandidateIds($snapshotPayload);
                if ($snapshotCandidateIds !== []) {
                    $hadExistingCategory = $hadExistingCategory || $this->categoryExistsForAnyCjId($snapshotCandidateIds);
                    $category = $resolver->resolveFromPayload($snapshotPayload, $createMissing && ! $dryRun);
                    if ($category) {
                        $resolvedFromSnapshots++;
                    }
                }
            }

            if (! $category) {
                if ($dryRun && $createMissing) {
                    $wouldCreate++;
                } else {
                    $unresolved++;
                }
                continue;
            }

            if (! $hadExistingCategory && ! $dryRun) {
                $createdPlaceholders++;
            }

            if (! $dryRun) {
                $product->category_id = $category->id;
                $product->save();
            }

            $assigned++;
        }

        $this->table(['Metric', 'Count'], [
            ['Scanned', $scanned],
            ['Assigned category_id', $assigned],
            ['Created placeholder categories', $createdPlaceholders],
            ['Resolved from snapshots', $resolvedFromSnapshots],
            ['Would create placeholders (dry-run)', $wouldCreate],
            ['No CJ payload', $noPayload],
            ['Unresolved', $unresolved],
        ]);

        if ($dryRun) {
            $this->info('Dry-run completed. No records were updated.');
            return self::SUCCESS;
        }

        $this->info('Backfill completed.');
        return self::SUCCESS;
    }

    private function extractPayload(Product $product): ?array
    {
        $payload = $product->cj_last_payload;
        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        $attributes = $product->attributes;
        if (is_array($attributes)) {
            $fallback = $attributes['cj_payload'] ?? null;
            if (is_array($fallback) && $fallback !== []) {
                return $fallback;
            }
        }

        return null;
    }

    private function extractSnapshotPayload(?CjProductSnapshot $snapshot): ?array
    {
        if (! $snapshot) {
            return null;
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $snapshotCategoryId = is_scalar($snapshot->category_id) ? trim((string) $snapshot->category_id) : '';

        if ($snapshotCategoryId !== '') {
            $payload['categoryId'] = $payload['categoryId'] ?? $snapshotCategoryId;
            $payload['cj_category_id'] = $payload['cj_category_id'] ?? $snapshotCategoryId;
        }

        if ($payload === []) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<int, string> $candidateIds
     */
    private function categoryExistsForAnyCjId(array $candidateIds): bool
    {
        return \App\Domain\Products\Models\Category::query()
            ->whereIn('cj_id', $candidateIds)
            ->exists();
    }
}
