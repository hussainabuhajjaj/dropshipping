<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CjCategorySyncService
{
    public function __construct(
        private readonly CJDropshippingClient $client,
    ) {
    }

    /**
     * Sync CJ category tree into local database
     * Preserves parent-child relationships and stores CJ IDs at every level
     */
    public function syncCategoryTree(): array
    {
        try {
            $response = $this->client->listCategories();
            
            if (!$response->success) {
                Log::error('CJ category sync failed', ['error' => $response->error ?? 'Unknown']);
                return [
                    'synced' => 0,
                    'errors' => 1,
                    'message' => 'CJ API Error: ' . ($response->error ?? 'Unknown error'),
                ];
            }

            $cjCategories = $response->data ?? [];
            
            if (!is_array($cjCategories)) {
                $cjCategories = (array) $cjCategories;
            }

            $synced = 0;
            $errors = 0;

            // Process each root category
            foreach ($cjCategories as $cjCat) {
                try {
                    $this->createOrUpdateCategory($cjCat);
                    $synced++;
                } catch (\Throwable $e) {
                    Log::error('Error syncing CJ category', [
                        'category' => $cjCat,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }

            return [
                'synced' => $synced,
                'errors' => $errors,
                'message' => "Synced {$synced} root categories",
            ];
        } catch (\Throwable $e) {
            Log::error('CJ category sync exception', ['error' => $e->getMessage()]);
            return [
                'synced' => 0,
                'errors' => 1,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Recursively create or update a category and its children
     */
    private function createOrUpdateCategory(array $cjCat, ?Category $parent = null): Category
    {
        $cjId = $this->extractCjId($cjCat);
        $name = $this->extractName($cjCat);

        if ($cjId === '' || $name === '') {
            throw new \Exception('Invalid CJ category data: missing id or name');
        }

        $slug = Str::slug($name);

        // Find or create by CJ ID (most reliable)
        $category = Category::query()
            ->where('cj_id', $cjId)
            ->first();

        if ($category) {
            // Update if name or parent changed
            $updates = [];
            if ($category->name !== $name) {
                $updates['name'] = $name;
                $updates['slug'] = $slug;
            }
            if ($category->parent_id !== $parent?->id) {
                $updates['parent_id'] = $parent?->id;
            }
            
            if (!empty($updates)) {
                $category->update($updates);
            }
        } else {
            // Create new category
            $category = Category::create([
                'cj_id' => $cjId,
                'name' => $name,
                'slug' => $slug,
                'parent_id' => $parent?->id,
            ]);
        }

        // Process children recursively
        if (isset($cjCat['children']) && is_array($cjCat['children'])) {
            foreach ($cjCat['children'] as $child) {
                try {
                    $this->createOrUpdateCategory($child, $category);
                } catch (\Throwable $e) {
                    Log::warning('Error syncing child category', [
                        'parent' => $name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $category;
    }

    private function extractCjId(array $cjCat): string
    {
        $id = $cjCat['categoryId']
            ?? $cjCat['id']
            ?? $cjCat['cj_id']
            ?? null;

        return is_scalar($id) ? (string) trim((string) $id) : '';
    }

    private function extractName(array $cjCat): string
    {
        $name = $cjCat['categoryName']
            ?? $cjCat['name']
            ?? $cjCat['category_name']
            ?? null;

        return is_scalar($name) ? (string) trim((string) $name) : '';
    }
}
