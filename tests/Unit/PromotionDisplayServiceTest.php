<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\Promotions\PromotionDisplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionDisplayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_targeted_and_sitewide_promotions_for_product_page(): void
    {
        config(['promotions.display.enabled' => true]);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $sitewide = Promotion::create([
            'name' => 'Logistics Support',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 5,
            'promotion_intent' => 'shipping_support',
            'display_placements' => ['product'],
            'stacking_rule' => 'combinable',
            'is_active' => true,
        ]);

        $targeted = Promotion::create([
            'name' => 'Category Boost',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 10,
            'promotion_intent' => 'cart_growth',
            'display_placements' => ['product'],
            'stacking_rule' => 'combinable',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $targeted->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);

        $service = app(PromotionDisplayService::class);
        $result = $service->getForPlacement('product', [$product->id], [$category->id], 5);

        $this->assertCount(2, $result);
        $this->assertTrue(collect($result)->contains('id', $sitewide->id));
        $this->assertTrue(collect($result)->contains('id', $targeted->id));

        $sitewideRow = collect($result)->firstWhere('id', $sitewide->id);
        $this->assertTrue($sitewideRow['is_sitewide']);
        $this->assertSame('shipping_support', $sitewideRow['intent']);
    }

    public function test_it_orders_by_priority_then_discount_then_end_date(): void
    {
        config(['promotions.display.enabled' => true]);

        $promotionA = Promotion::create([
            'name' => 'A',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 5,
            'priority' => 1,
            'promotion_intent' => 'cart_growth',
            'display_placements' => ['home'],
            'stacking_rule' => 'combinable',
            'is_active' => true,
        ]);

        $promotionB = Promotion::create([
            'name' => 'B',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 20,
            'priority' => 1,
            'promotion_intent' => 'cart_growth',
            'display_placements' => ['home'],
            'stacking_rule' => 'combinable',
            'is_active' => true,
        ]);

        $service = app(PromotionDisplayService::class);
        $result = $service->getForPlacement('home', [], [], 5);

        $this->assertSame($promotionB->id, $result[0]['id']);
        $this->assertSame($promotionA->id, $result[1]['id']);
    }
}
