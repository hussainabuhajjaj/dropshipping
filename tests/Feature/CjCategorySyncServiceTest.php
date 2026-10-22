<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Products\Services\CjCategorySyncService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\Category;
use App\Services\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CjCategorySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_cj_get_category_three_level_structure_into_categories_table(): void
    {
        $payload = [
            [
                'categoryFirstId' => 'FIRST-1',
                'categoryFirstName' => "Women's Clothing",
                'categoryFirstList' => [
                    [
                        'categorySecondId' => 'SECOND-1',
                        'categorySecondName' => 'Accessories',
                        'categorySecondList' => [
                            ['categoryId' => 'THIRD-1', 'categoryName' => 'Scarves & Wraps'],
                            ['categoryId' => 'THIRD-2', 'categoryName' => 'Belts & Cummerbunds'],
                        ],
                    ],
                    [
                        'categorySecondId' => 'SECOND-2',
                        'categorySecondName' => 'Tops & Sets',
                        'categorySecondList' => [
                            ['categoryId' => 'THIRD-3', 'categoryName' => 'Blouses & Shirts'],
                            ['categoryId' => 'THIRD-4', 'categoryName' => 'Sweaters'],
                        ],
                    ],
                ],
            ],
        ];

        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('listCategories')->once()->andReturn(ApiResponse::success($payload));

        $service = app(CjCategorySyncService::class);
        $result = $service->syncCategoryTree();

        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['errors']);

        $this->assertDatabaseHas('categories', ['cj_id' => 'FIRST-1', 'name' => "Women's Clothing"]);
        $root = Category::query()->where('cj_id', 'FIRST-1')->firstOrFail();
        $this->assertNull($root->parent_id);
        $this->assertIsArray($root->cj_payload);
        $this->assertSame('FIRST-1', $root->cj_payload['categoryFirstId'] ?? null);

        $this->assertDatabaseHas('categories', ['cj_id' => 'SECOND-1', 'name' => 'Accessories', 'parent_id' => $root->id]);
        $second = Category::query()->where('cj_id', 'SECOND-1')->firstOrFail();
        $this->assertIsArray($second->cj_payload);
        $this->assertSame('SECOND-1', $second->cj_payload['categorySecondId'] ?? null);

        $this->assertDatabaseHas('categories', ['cj_id' => 'THIRD-1', 'name' => 'Scarves & Wraps', 'parent_id' => $second->id]);
        $this->assertDatabaseHas('categories', ['cj_id' => 'THIRD-2', 'name' => 'Belts & Cummerbunds', 'parent_id' => $second->id]);

        $this->assertSame(1 + 2 + 4, Category::query()->count());
    }

    public function test_it_updates_existing_categories_by_cj_id(): void
    {
        $payload = [
            [
                'categoryFirstId' => 'FIRST-1',
                'categoryFirstName' => "Women's Clothing",
                'categoryFirstList' => [],
            ],
        ];

        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('listCategories')->once()->andReturn(ApiResponse::success($payload));

        $service = app(CjCategorySyncService::class);
        $service->syncCategoryTree();

        $this->assertDatabaseHas('categories', ['cj_id' => 'FIRST-1', 'name' => "Women's Clothing"]);

        $payload[0]['categoryFirstName'] = "Womens Clothing (Updated)";
        $client = $this->mock(CJDropshippingClient::class);
        $client->shouldReceive('listCategories')->once()->andReturn(ApiResponse::success($payload));

        $service = app(CjCategorySyncService::class);
        $service->syncCategoryTree();

        $this->assertDatabaseHas('categories', ['cj_id' => 'FIRST-1', 'name' => 'Womens Clothing (Updated)']);
        $this->assertSame(1, Category::query()->count());
    }
}

