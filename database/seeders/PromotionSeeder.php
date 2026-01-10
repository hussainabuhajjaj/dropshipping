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
        $faker = \Faker\Factory::create();
        $categories = \App\Models\Category::all();
        $products = \App\Models\Product::all();

        // Create a promotion for every category
        foreach ($categories as $category) {
            $promo = Promotion::create([
                'name' => $faker->randomElement([
                    'Flash Sale', 'Mega Discount', 'Category Special', 'Limited Time Offer', 'Seasonal Sale'
                ]) . ' - ' . $category->name,
                'description' => $faker->sentence(),
                'type' => $faker->randomElement(['flash_sale', 'auto_discount']),
                'value_type' => $faker->randomElement(['percentage', 'fixed']),
                'value' => $faker->randomFloat(2, 10, 40),
                'start_at' => now()->subDays($faker->numberBetween(0, 3)),
                'end_at' => now()->addDays($faker->numberBetween(2, 14)),
                'priority' => $faker->numberBetween(1, 20),
                'is_active' => true,
                'stacking_rule' => $faker->randomElement(['exclusive', 'combinable']),
            ]);
            PromotionTarget::create([
                'promotion_id' => $promo->id,
                'target_type' => 'category',
                'target_id' => $category->id,
            ]);
        }

        // Create promotions for most products (80%)
        $productCount = $products->count();
        $targetProducts = $products->random(floor($productCount * 0.8));
        foreach ($targetProducts as $product) {
            $promo = Promotion::create([
                'name' => $faker->randomElement([
                    'Product Launch', 'Hot Deal', 'Exclusive Offer', 'Limited Time', 'Special Discount'
                ]) . ' - ' . $product->name,
                'description' => $faker->sentence(),
                'type' => $faker->randomElement(['auto_discount', 'flash_sale']),
                'value_type' => $faker->randomElement(['fixed', 'percentage']),
                'value' => $faker->randomFloat(2, 5, 30),
                'start_at' => now()->subDays($faker->numberBetween(0, 2)),
                'end_at' => now()->addDays($faker->numberBetween(3, 10)),
                'priority' => $faker->numberBetween(1, 15),
                'is_active' => true,
                'stacking_rule' => $faker->randomElement(['exclusive', 'combinable']),
            ]);
            PromotionTarget::create([
                'promotion_id' => $promo->id,
                'target_type' => 'product',
                'target_id' => $product->id,
            ]);
        }

        // Create several cart value promotions
        for ($i = 0; $i < 5; $i++) {
            $cartDiscount = Promotion::create([
                'name' => 'Cart Discount - $' . (10 + $i * 5) . ' Off',
                'description' => '$' . (10 + $i * 5) . ' off orders over $' . (100 + $i * 50),
                'type' => 'auto_discount',
                'value_type' => 'fixed',
                'value' => 10 + $i * 5,
                'start_at' => now()->subDays($faker->numberBetween(0, 1)),
                'end_at' => now()->addMonth(),
                'priority' => 5 + $i,
                'is_active' => true,
                'stacking_rule' => 'combinable',
            ]);
            PromotionCondition::create([
                'promotion_id' => $cartDiscount->id,
                'condition_type' => 'min_cart_value',
                'condition_value' => 100 + $i * 50,
            ]);
        }
    }
}
