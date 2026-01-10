<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\PromotionCondition;

class PromotionSeeder extends Seeder
{
    public function run()
    {
        // Get a real category and product for demo targets
        $category = \App\Models\Category::first();
        $product = \App\Models\Product::first();

        // Flash Sale on first category
        $flashSale = Promotion::create([
            'name' => 'Flash Sale - 20% Off',
            'description' => '20% off all products in ' . ($category ? $category->name : 'Category 1') . ' for 24 hours',
            'type' => 'flash_sale',
            'value_type' => 'percentage',
            'value' => 20,
            'start_at' => now(),
            'end_at' => now()->addDay(),
            'priority' => 10,
            'is_active' => true,
            'stacking_rule' => 'exclusive',
        ]);
        if ($category) {
            PromotionTarget::create([
                'promotion_id' => $flashSale->id,
                'target_type' => 'category',
                'target_id' => $category->id,
            ]);
        }

        // Product-specific promotion
        if ($product) {
            $productPromo = Promotion::create([
                'name' => 'Product Launch - $15 Off',
                'description' => 'Save $15 on ' . $product->name . ' for a limited time',
                'type' => 'auto_discount',
                'value_type' => 'fixed',
                'value' => 15,
                'start_at' => now(),
                'end_at' => now()->addDays(7),
                'priority' => 8,
                'is_active' => true,
                'stacking_rule' => 'combinable',
            ]);
            PromotionTarget::create([
                'promotion_id' => $productPromo->id,
                'target_type' => 'product',
                'target_id' => $product->id,
            ]);
        }

        // Cart Discount for Orders Over $100
        $cartDiscount = Promotion::create([
            'name' => 'Cart Discount - $10 Off',
            'description' => '$10 off orders over $100',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 10,
            'start_at' => now(),
            'end_at' => now()->addMonth(),
            'priority' => 5,
            'is_active' => true,
            'stacking_rule' => 'combinable',
        ]);
        PromotionCondition::create([
            'promotion_id' => $cartDiscount->id,
            'condition_type' => 'min_cart_value',
            'condition_value' => '100',
        ]);
    }
}
