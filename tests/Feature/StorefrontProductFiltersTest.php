<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StorefrontCampaign;
use App\Models\StorefrontCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_can_filter_by_collection_slug(): void
    {
        [$inScope, $outOfScope] = $this->seedTwoProducts();

        $collection = StorefrontCollection::query()->create([
            'title' => 'Weekend Drop',
            'slug' => 'weekend-drop',
            'type' => 'drop',
            'is_active' => true,
            'selection_mode' => 'manual',
            'manual_products' => [
                ['product_id' => $inScope->id, 'position' => 1],
            ],
        ]);

        $response = $this->get('/products?collection=' . $collection->slug);

        $response->assertStatus(200);
        $response->assertSee('In scope product');
        $response->assertDontSee('Out of scope product');
    }

    public function test_products_index_can_filter_by_campaign_slug(): void
    {
        [$inScope, $outOfScope] = $this->seedTwoProducts();

        $collection = StorefrontCollection::query()->create([
            'title' => 'Holiday Set',
            'slug' => 'holiday-set',
            'type' => 'seasonal',
            'is_active' => true,
            'selection_mode' => 'manual',
            'manual_products' => [
                ['product_id' => $inScope->id, 'position' => 1],
            ],
        ]);

        $campaign = StorefrontCampaign::query()->create([
            'name' => 'Holiday Campaign',
            'slug' => 'holiday-campaign',
            'type' => 'seasonal',
            'status' => 'active',
            'is_active' => true,
            'collection_ids' => [$collection->id],
            'placements' => ['home_hero'],
        ]);

        $response = $this->get('/products?campaign=' . $campaign->slug);

        $response->assertStatus(200);
        $response->assertSee('In scope product');
        $response->assertDontSee('Out of scope product');
    }

    public function test_products_index_can_filter_by_flash_sale_promotion_type(): void
    {
        [$inScope, $outOfScope] = $this->seedTwoProducts();

        $promotion = Promotion::query()->create([
            'name' => 'Flash Friday',
            'description' => 'Limited price drop',
            'type' => 'flash_sale',
            'value_type' => 'percentage',
            'value' => 10,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'priority' => 50,
            'is_active' => true,
            'stacking_rule' => 'exclusive',
        ]);

        $promotion->targets()->create([
            'target_type' => 'product',
            'target_id' => $inScope->id,
        ]);

        $response = $this->get('/products?promotion_type=flash_sale');

        $response->assertStatus(200);
        $response->assertSee('In scope product');
        $response->assertDontSee('Out of scope product');
    }

    private function seedTwoProducts(): array
    {
        $category = Category::factory()->create();

        $inScope = Product::factory()->create([
            'name' => 'In scope product',
            'slug' => 'in-scope-product',
            'is_active' => true,
            'category_id' => $category->id,
            'selling_price' => 50,
        ]);

        $outOfScope = Product::factory()->create([
            'name' => 'Out of scope product',
            'slug' => 'out-of-scope-product',
            'is_active' => true,
            'category_id' => $category->id,
            'selling_price' => 60,
        ]);

        return [$inScope, $outOfScope];
    }

}
