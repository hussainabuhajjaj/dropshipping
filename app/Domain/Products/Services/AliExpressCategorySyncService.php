<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AliExpressCategorySyncService
{
    public function __construct(
        private readonly AliExpressClient $client,
    ) {}

    public function syncCategories(): array
    {
        try {
            $response = $this->client->getCategories();

            $categories = collect($response['resp_result']['result']['categories'] ?? [])
                ->filter(fn ($cat) => ! empty($cat['category_id']))
                ->sortBy(fn ($cat) => (int) ($cat['category_level'] ?? 0))
                ->values();

            if ($categories->isEmpty()) {
                Log::warning('AliExpress returned no categories');
                return [];
            }

            $synced = [];
            $metaByAliId = [];

            foreach ($categories as $catData) {
                $aliCategoryId = (string) ($catData['category_id'] ?? null);
                if ($aliCategoryId === '') {
                    continue;
                }

                $category = Category::updateOrCreate(
                    ['ali_category_id' => $aliCategoryId],
                    [
                        'name' => $catData['category_name'] ?? $catData['name'] ?? 'Unknown',
                        'slug' => Str::slug($catData['category_name'] ?? $catData['name'] ?? "ali-{$aliCategoryId}"),
                        'description' => $catData['category_description'] ?? $catData['category_name'] ?? null,

                        'ali_payload' => $response,
                    ]
                );

                $synced[] = $category;
                $metaByAliId[$aliCategoryId] = [
                    'model' => $category,
                    'parent' => (string) ($catData['parent_category_id'] ?? ''),
                ];
            }

            foreach ($metaByAliId as $aliCategoryId => $payload) {
                $parentAliId = $payload['parent'];
                if ($parentAliId === '' || ! isset($metaByAliId[$parentAliId])) {
                    continue;
                }

                $payload['model']->updateQuietly([
                    'parent_id' => $metaByAliId[$parentAliId]['model']->id,
                ]);
            }

            $this->logScopeCoverage($categories, $synced);
            Log::info('AliExpress categories synced', ['count' => count($synced)]);

            return $synced;
        } catch (\Exception $e) {
            Log::error('AliExpress category sync failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function logScopeCoverage($categories, array $synced): void
    {
        $apiIds = $categories->pluck('category_id')->filter()->map(fn ($value) => strval($value))->unique()->values();
        $syncedIds = collect($synced)->pluck('ali_category_id')->filter()->map(fn ($value) => strval($value))->unique();
        $missing = $apiIds->diff($syncedIds);
        if ($missing->isNotEmpty()) {
            Log::warning('AliExpress categories missing from sync result', [
                'count' => $missing->count(),
                'sample_ids' => $missing->take(10)->values(),
            ]);
        }

        $levels = $categories
            ->map(fn ($c) => (int) ($c['category_level'] ?? 0))
            ->filter(fn ($level) => $level > 0)
            ->countBy()
            ->sortKeys();

        Log::info('AliExpress category level coverage', [
            'levels' => $levels,
            'total_api' => $apiIds->count(),
        ]);
    }
}
