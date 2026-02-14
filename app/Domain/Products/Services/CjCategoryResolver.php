<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CjCategoryResolver
{
    /**
     * Resolve a category from CJ payload. Optionally creates a placeholder when
     * category IDs are present but not yet synced in local categories.
     */
    public function resolveFromPayload(array $payload, bool $createMissing = true): ?Category
    {
        $candidateIds = $this->extractCandidateIds($payload);

        if ($candidateIds === []) {
            return null;
        }

        foreach ($candidateIds as $cjId) {
            $existing = Category::query()->where('cj_id', $cjId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! $createMissing) {
            return null;
        }

        $primaryId = $candidateIds[0];
        $candidateNames = $this->extractCandidateNames($payload);
        $name = $this->buildPlaceholderName($primaryId, $candidateNames);
        $slug = $this->buildPlaceholderSlug($primaryId, $name);

        $category = Category::query()->firstOrCreate(
            ['cj_id' => $primaryId],
            [
                'name' => $name,
                'slug' => $slug,
                'description' => 'Auto-created placeholder category from CJ product import. Run cj:sync-categories to hydrate official taxonomy.',
                'cj_payload' => [
                    'source' => 'product_import_placeholder',
                    'category_ids' => $candidateIds,
                    'category_names' => $candidateNames,
                ],
            ]
        );

        if ($category->wasRecentlyCreated) {
            Log::warning('Created placeholder CJ category during product import', [
                'category_id' => $category->id,
                'cj_id' => $primaryId,
                'name' => $category->name,
            ]);
        }

        return $category;
    }

    /**
     * @return array<int, string>
     */
    public function extractCandidateIds(array $payload): array
    {
        // Prefer deepest known category IDs first.
        $preferredKeys = [
            'categoryId',
            'threeCategoryId',
            'categoryThirdId',
            'twoCategoryId',
            'categorySecondId',
            'oneCategoryId',
            'categoryFirstId',
            'category_id',
        ];

        $rawValues = $this->collectValuesForKeys($payload, $preferredKeys);
        $ids = [];

        foreach ($rawValues as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $id = trim((string) $value);
            if ($id === '' || strtolower($id) === 'null') {
                continue;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, string>
     */
    public function extractCandidateNames(array $payload): array
    {
        $preferredKeys = [
            'categoryName',
            'categoryNameEn',
            'threeCategoryName',
            'categoryThirdName',
            'twoCategoryName',
            'categorySecondName',
            'oneCategoryName',
            'categoryFirstName',
            'category_name',
        ];

        $rawValues = $this->collectValuesForKeys($payload, $preferredKeys);
        $names = [];

        foreach ($rawValues as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $name = trim((string) $value);
            if ($name === '' || strtolower($name) === 'null') {
                continue;
            }

            $names[] = preg_replace('/\s+/', ' ', $name) ?? $name;
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<int, string> $candidateNames
     */
    private function buildPlaceholderName(string $cjId, array $candidateNames): string
    {
        if ($candidateNames !== []) {
            $base = trim((string) Str::limit($candidateNames[0], 90, ''));
            if ($base !== '') {
                return $base . ' [CJ ' . $cjId . ']';
            }
        }

        return 'CJ Category ' . $cjId;
    }

    private function buildPlaceholderSlug(string $cjId, string $name): string
    {
        $slugBase = Str::slug($name);

        if ($slugBase === '') {
            $slugBase = 'cj-category';
        }

        return Str::limit($slugBase, 180, '') . '-' . Str::slug($cjId);
    }

    /**
     * @param array<int, string> $keys
     * @return array<int, mixed>
     */
    private function collectValuesForKeys(array $payload, array $keys): array
    {
        $values = [];
        $lookup = array_fill_keys(array_map(static fn (string $key): string => strtolower($key), $keys), true);

        $walk = function (mixed $node) use (&$walk, &$values, $lookup): void {
            if (! is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                if (is_string($key) && isset($lookup[strtolower($key)])) {
                    $values[] = $value;
                }

                if (is_array($value)) {
                    $walk($value);
                }
            }
        };

        $walk($payload);

        return $values;
    }
}

