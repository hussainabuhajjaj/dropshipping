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

    public function test_it_stacks_shipping_support_with_cart_growth(): void
    {
        $category = Category::factory()->create();

        $shippingSupport = Promotion::create([
            'name' => 'Logistics Support',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 5,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'shipping_support',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $shippingSupport->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);

        $cartGrowth = Promotion::create([
            'name' => 'Cart Growth',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 10,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'cart_growth',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $cartGrowth->id,
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

        $this->assertCount(2, $result['discounts']);
        $this->assertEquals(15.0, $result['total_discount']);
    }

    public function test_urgency_exclusive_overrides_other_promotions(): void
    {
        $category = Category::factory()->create();

        $urgency = Promotion::create([
            'name' => 'Flash',
            'type' => 'flash_sale',
            'value_type' => 'percentage',
            'value' => 20,
            'stacking_rule' => 'exclusive',
            'promotion_intent' => 'urgency',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $urgency->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);

        $shippingSupport = Promotion::create([
            'name' => 'Support',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 5,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'shipping_support',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $shippingSupport->id,
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
        $this->assertSame($urgency->id, $result['discounts'][0]['promotion_id']);
        $this->assertEquals(20.0, $result['total_discount']);
    }

    public function test_max_discount_condition_caps_promotion(): void
    {
        $category = Category::factory()->create();

        $promotion = Promotion::create([
            'name' => 'Cap Test',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 50,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'cart_growth',
            'is_active' => true,
        ]);
        PromotionTarget::create([
            'promotion_id' => $promotion->id,
            'target_type' => 'category',
            'target_id' => $category->id,
        ]);
        \App\Models\PromotionCondition::create([
            'promotion_id' => $promotion->id,
            'condition_type' => 'max_discount',
            'condition_value' => '10',
        ]);

        $engine = app(PromotionEngine::class);
        $result = $engine->applyPromotions([
            'lines' => [
                ['product_id' => 1, 'category_id' => $category->id],
            ],
            'subtotal' => 100,
            'user_id' => null,
        ]);

        $this->assertEquals(10.0, $result['total_discount']);
    }
}
