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
            $categories = $response['data'] ?? [];
            
            if (!is_array($categories) || $categories === []) {
                Log::warning('AliExpress returned no categories');
                return [];
            }
            
            $synced = [];
            foreach ($categories as $catData) {
                $category = Category::updateOrCreate(
                    ['name' => $catData['cate_name'] ?? $catData['name'] ?? 'Unknown'],
                    [
                        'slug' => Str::slug($catData['cate_name'] ?? $catData['name'] ?? 'unknown'),
                        'description' => $catData['cate_desc'] ?? null,
                        'attributes' => [
                            'ali_category_id' => $catData['cate_id'] ?? $catData['id'] ?? null,
                            'parent_id' => $catData['parent_id'] ?? null,
                        ],
                    ]
                );
                $synced[] = $category;
            }
            
            Log::info('AliExpress categories synced', ['count' => count($synced)]);
            
            return $synced;
        } catch (\Exception $e) {
            Log::error('AliExpress category sync failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
