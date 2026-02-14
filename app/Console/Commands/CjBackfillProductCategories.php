<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjCategoryResolver;
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

        foreach ($products as $product) {
            $scanned++;

            $payload = $this->extractPayload($product);
            if ($payload === null) {
                $noPayload++;
                continue;
            }

            $candidateIds = $resolver->extractCandidateIds($payload);
            if ($candidateIds === []) {
                $unresolved++;
                continue;
            }

            $hadExistingCategory = $this->categoryExistsForAnyCjId($candidateIds);
            $category = $resolver->resolveFromPayload($payload, $createMissing && ! $dryRun);

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

