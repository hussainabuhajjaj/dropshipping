<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Services\CjCategoryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CjNormalizeCategoryNames extends Command
{
    protected $signature = 'cj:normalize-category-names
        {--limit=10000 : Maximum number of categories to process}
        {--dry-run : Preview changes without updating records}';

    protected $description = 'Normalize legacy CJ category names (breadcrumbs and [CJ ...] suffixes).';

    public function handle(CjCategoryResolver $resolver): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $categories = Category::query()
            ->whereNotNull('cj_id')
            ->where(function ($query): void {
                $query->where('name', 'like', '%>%')
                    ->orWhere('name', 'like', '%[CJ%');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($categories->isEmpty()) {
            $this->info('No legacy CJ category names found.');
            return self::SUCCESS;
        }

        $scanned = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            $scanned++;
            $oldName = (string) $category->name;

            $preferred = $resolver->normalizeCategoryDisplayName($oldName);
            if ($preferred === '') {
                $payloadName = is_array($category->cj_payload) ? ($category->cj_payload['categoryName'] ?? null) : null;
                if (is_string($payloadName) && $payloadName !== '') {
                    $preferred = $resolver->normalizeCategoryDisplayName($payloadName);
                }
            }

            if ($preferred === '' || $preferred === $oldName) {
                $skipped++;
                continue;
            }

            $normalized = $this->resolveUniqueName($category, $preferred);

            if (! $dryRun) {
                $category->name = $normalized;
                $category->slug = Str::slug($normalized);
                $category->save();
            }

            $updated++;
            $this->line(sprintf(
                '[%s] #%d "%s" => "%s"',
                $dryRun ? 'DRY' : 'OK',
                (int) $category->id,
                $oldName,
                $normalized
            ));
        }

        $this->table(['Metric', 'Count'], [
            ['Scanned', $scanned],
            ['Updated', $updated],
            ['Skipped', $skipped],
        ]);

        if ($dryRun) {
            $this->info('Dry-run completed. No records were changed.');
        } else {
            $this->info('Normalization completed.');
        }

        return self::SUCCESS;
    }

    private function resolveUniqueName(Category $category, string $preferred): string
    {
        $preferred = trim($preferred);
        if ($preferred === '') {
            $preferred = 'CJ Category';
        }

        $candidate = $preferred;
        $suffixBase = ' (CJ ' . strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $category->cj_id), -8)) . ')';
        if ($suffixBase === ' (CJ )') {
            $suffixBase = ' (CJ)';
        }

        $attempt = 0;
        while ($this->nameExistsForSibling($category, $candidate)) {
            $attempt++;
            $indexedSuffix = $attempt === 1 ? $suffixBase : $suffixBase . '-' . $attempt;
            $candidate = Str::limit($preferred, max(1, 200 - strlen($indexedSuffix)), '') . $indexedSuffix;
        }

        return $candidate;
    }

    private function nameExistsForSibling(Category $category, string $name): bool
    {
        $query = Category::query()
            ->where('id', '!=', $category->id)
            ->where('name', $name);

        if ($category->parent_id === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $category->parent_id);
        }

        return $query->exists();
    }
}

