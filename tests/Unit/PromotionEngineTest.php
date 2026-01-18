<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_exclusive_promotion_over_combinable(): void
    {
        $category = Category::factory()->create();

        $promoCombinable = Promotion::create([
            'name' => 'Combinable',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 10,
            'stacking_rule' => 'combinable',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promoCombinable->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);

        $promoExclusive = Promotion::create([
            'name' => 'Exclusive',
            'type' => 'flash_sale',
            'value_type' => 'percentage',
            'value' => 20,
            'stacking_rule' => 'exclusive',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promoExclusive->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);

        $engine = app(PromotionEngine::class);
        $result = $engine->applyPromotions([
            'lines' => [
                ['product_id' => 1, 'category_id' => $category->id],
            ],
            'subtotal' => 100,
            'user_id' => null,
        ]);

        $this->assertCount(1, $result['discounts']);
        $this->assertSame($promoExclusive->id, $result['discounts'][0]['promotion_id']);
        $this->assertEquals(20.0, $result['total_discount']);
    }

    public function test_it_matches_any_target_instead_of_all(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $categoryA->id]);

        $promotion = Promotion::create([
            'name' => 'Mixed Targets',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 5,
            'stacking_rule' => 'combinable',
            'is_active' => true,
        ]);

        PromotionTarget::create([
            'promotion_id' => $promotion->id,
            'target_type' => 'category',
            'target_id' => $categoryB->id,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promotion->id,
            'target_type' => 'product',
            'target_id' => $product->id,
        ]);

        $engine = app(PromotionEngine::class);
        $promotions = $engine->getApplicablePromotions([
            'lines' => [
                ['product_id' => $product->id, 'category_id' => $categoryA->id],
            ],
            'subtotal' => 50,
            'user_id' => null,
        ]);

        $this->assertTrue($promotions->contains('id', $promotion->id));
    }
}
