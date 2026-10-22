<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Domain\Products\Models\Category;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Facades\DB;
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
        $debug = filter_var(env('CJ_DEBUG', false), FILTER_VALIDATE_BOOL);
        $endpoint = '/v1/product/getCategory';
        $baseUrl = rtrim((string) config('services.cj.base_url', ''), '/');
        $url = $baseUrl !== '' ? $baseUrl . $endpoint : $endpoint;

        $synced = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        Log::info('CJ category import started');

        try {
            $response = $this->client->listCategories();

            $raw = $response->raw;
            $requestId = (is_array($raw) && is_scalar($raw['requestId'] ?? null)) ? (string) $raw['requestId'] : null;

            if ($debug) {
                Log::debug('CJ listCategories response', [
                    'url' => $url,
                    'status' => $response->status,
                    'requestId' => $requestId,
                ]);
            }

            if (! $response->ok) {
                Log::error('CJ category import failed', [
                    'url' => $url,
                    'status' => $response->status,
                    'requestId' => $requestId,
                    'message' => $response->message,
                ]);

                return [
                    'synced' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'errors' => 1,
                    'message' => 'CJ API Error: ' . ($response->message ?? 'Unknown error'),
                ];
            }

            $firstList = $this->unwrapList($response->data ?? []);
            $levelCounts = $this->countThreeLevelPayload($firstList);

            if ($debug) {
                Log::debug('CJ category payload counts', [
                    'requestId' => $requestId,
                    'level1' => $levelCounts['level1'],
                    'level2' => $levelCounts['level2'],
                    'level3' => $levelCounts['level3'],
                ]);
            }

            foreach ($firstList as $first) {
                try {
                    DB::transaction(function () use (&$created, &$updated, &$synced, $first): void {
                        $firstId = $this->stringOrEmpty($first['categoryFirstId'] ?? null);
                        $firstName = $this->stringOrEmpty($first['categoryFirstName'] ?? null);
                        $firstSlug = Str::slug($firstName);

                        $firstCategory = $this->upsertCategory(
                            cjId: $firstId,
                            name: $firstName,
                            slug: $firstSlug,
                            parentId: null,
                            payload: $first,
                            created: $created,
                            updated: $updated,
                        );

                        $secondList = $first['categoryFirstList'] ?? [];
                        if (! is_array($secondList) || ! $firstCategory) {
                            $synced++;
                            return;
                        }

                        foreach ($secondList as $second) {
                            if (! is_array($second)) {
                                continue;
                            }

                            $secondId = $this->stringOrEmpty($second['categorySecondId'] ?? null);
                            $secondName = $this->stringOrEmpty($second['categorySecondName'] ?? null);
                            $secondSlug = Str::slug(trim($firstName . ' ' . $secondName));

                            $secondCategory = $this->upsertCategory(
                                cjId: $secondId,
                                name: $secondName,
                                slug: $secondSlug,
                                parentId: $firstCategory->id,
                                payload: $second,
                                created: $created,
                                updated: $updated,
                            );

                            $thirdList = $second['categorySecondList'] ?? [];
                            if (! is_array($thirdList) || ! $secondCategory) {
                                continue;
                            }

                            foreach (array_chunk($thirdList, 250) as $chunk) {
                                foreach ($chunk as $third) {
                                    if (! is_array($third)) {
                                        continue;
                                    }

                                    $thirdId = $this->stringOrEmpty($third['categoryId'] ?? null);
                                    $thirdName = $this->stringOrEmpty($third['categoryName'] ?? null);
                                    $thirdSlug = Str::slug(trim($firstName . ' ' . $secondName . ' ' . $thirdName));

                                    $this->upsertCategory(
                                        cjId: $thirdId,
                                        name: $thirdName,
                                        slug: $thirdSlug,
                                        parentId: $secondCategory->id,
                                        payload: $third,
                                        created: $created,
                                        updated: $updated,
                                    );
                                }
                            }
                        }

                        $synced++;
                    }, 3);
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('Error importing CJ category subtree', [
                        'category' => $first,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('CJ category import completed', [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'level1' => $levelCounts['level1'],
                'level2' => $levelCounts['level2'],
                'level3' => $levelCounts['level3'],
            ]);

            return [
                'synced' => $synced,
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'levels' => $levelCounts,
                'message' => "Synced {$synced} root categories",
            ];
        } catch (\Throwable $e) {
            Log::error('CJ category import exception', ['error' => $e->getMessage()]);
            return [
                'synced' => 0,
                'created' => $created,
                'updated' => $updated,
                'errors' => max(1, $errors),
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    private function upsertCategory(
        string $cjId,
        string $name,
        string $slug,
        ?int $parentId,
        array $payload,
        int &$created,
        int &$updated,
    ): ?Category
    {
        if ($cjId === '' || $name === '') {
            return null;
        }

        $category = Category::query()->where('cj_id', $cjId)->first();
        if (! $category) {
            $category = new Category();
        }

        $wasNew = ! $category->exists;

        $category->fill([
            'cj_id' => $cjId,
            'cj_payload' => $payload,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
        ]);

        $dirty = $category->isDirty(['cj_payload', 'name', 'slug', 'parent_id', 'cj_id']);
        $category->save();

        if ($wasNew) {
            $created++;
        } elseif ($dirty) {
            $updated++;
        }

        return $category;
    }

    private function unwrapList(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_key_exists('list', $data) && is_array($data['list'])) {
            $data = $data['list'];
        }

        if (! array_is_list($data)) {
            $data = [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private function countThreeLevelPayload(array $firstList): array
    {
        $level1 = 0;
        $level2 = 0;
        $level3 = 0;

        foreach ($firstList as $first) {
            $level1++;

            $seconds = $first['categoryFirstList'] ?? [];
            if (! is_array($seconds)) {
                continue;
            }

            $level2 += count($seconds);
            foreach ($seconds as $second) {
                if (! is_array($second)) {
                    continue;
                }

                $thirds = $second['categorySecondList'] ?? [];
                if (is_array($thirds)) {
                    $level3 += count($thirds);
                }
            }
        }

        return ['level1' => $level1, 'level2' => $level2, 'level3' => $level3];
    }

    private function stringOrEmpty(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
