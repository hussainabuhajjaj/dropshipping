<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\PromotionCondition;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class PromotionSeeder extends Seeder
{
    public function run()
    {
        $now = now();
        $categories = Category::query()->withCount('products')->orderByDesc('products_count')->get();
        $products = Product::query()->where('is_active', true)->orderByDesc('selling_price')->get();

        if ($categories->isEmpty() && $products->isEmpty()) {
            $this->command?->warn('PromotionSeeder: no categories/products found. Seed catalog first.');
            return;
        }

        $createPromotion = function (array $data, array $targets = [], array $conditions = []): Promotion {
            $promo = Promotion::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            PromotionTarget::where('promotion_id', $promo->id)->delete();
            PromotionCondition::where('promotion_id', $promo->id)->delete();

            foreach ($targets as $target) {
                PromotionTarget::create([
                    'promotion_id' => $promo->id,
                    'target_type' => $target['type'],
                    'target_id' => $target['id'],
                ]);
            }

            foreach ($conditions as $condition) {
                PromotionCondition::create([
                    'promotion_id' => $promo->id,
                    'condition_type' => $condition['type'],
                    'condition_value' => (string) $condition['value'],
                ]);
            }

            return $promo;
        };

        // 1) Shipping support (site-wide, conditional) - reduce shipping sticker shock.
        $createPromotion([
            'name' => 'Seeded: Logistics Support - Free shipping relief',
            'description' => 'We support your logistics cost on qualifying orders.',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 4.00,
            'start_at' => $now->subHours(1),
            'end_at' => $now->addDays(10),
            'priority' => 15,
            'is_active' => true,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'shipping_support',
            'display_placements' => ['home', 'category', 'product', 'cart', 'checkout'],
        ], [], [
            ['type' => 'min_cart_value', 'value' => 25],
            ['type' => 'max_discount', 'value' => 8],
        ]);

        // 2) Cart growth tiers (site-wide, conditional).
        $tiers = [
            ['min' => 40, 'value' => 6, 'priority' => 12],
            ['min' => 70, 'value' => 10, 'priority' => 11],
            ['min' => 100, 'value' => 15, 'priority' => 10],
        ];
        foreach ($tiers as $tier) {
            $createPromotion([
                'name' => 'Seeded: Cart Booster - Save $' . $tier['value'] . ' over $' . $tier['min'],
                'description' => 'Add more items to unlock bigger savings.',
                'type' => 'auto_discount',
                'value_type' => 'fixed',
                'value' => $tier['value'],
                'start_at' => $now->subHours(2),
                'end_at' => $now->addDays(14),
                'priority' => $tier['priority'],
                'is_active' => true,
                'stacking_rule' => 'combinable',
                'promotion_intent' => 'cart_growth',
                'display_placements' => ['cart', 'checkout', 'category', 'product'],
            ], [], [
                ['type' => 'min_cart_value', 'value' => $tier['min']],
                ['type' => 'max_discount', 'value' => $tier['value']],
            ]);
        }

        // 3) Category boosters (targeted, product/category pages).
        $topCategories = $categories->filter(fn ($c) => $c->products_count > 0)->take(4);
        foreach ($topCategories as $category) {
            $label = Str::limit($category->name, 30);
            $createPromotion([
                'name' => 'Seeded: Category Boost - ' . $label,
                'description' => 'Limited offer on this category.',
                'type' => 'auto_discount',
                'value_type' => 'percentage',
                'value' => 12,
                'start_at' => $now->subHours(3),
                'end_at' => $now->addDays(7),
                'priority' => 9,
                'is_active' => true,
                'stacking_rule' => 'combinable',
                'promotion_intent' => 'cart_growth',
                'display_placements' => ['category', 'product'],
            ], [
                ['type' => 'category', 'id' => $category->id],
            ]);
        }

        // 4) Product flash deals (targeted, urgency).
        $flashProducts = $products->take(5);
        foreach ($flashProducts as $product) {
            $label = Str::limit($product->name, 32);
            $createPromotion([
                'name' => 'Seeded: Flash Deal - ' . $label,
                'description' => 'Short window, limited time pricing.',
                'type' => 'flash_sale',
                'value_type' => 'percentage',
                'value' => 18,
                'start_at' => $now->subHours(1),
                'end_at' => $now->addDays(2),
                'priority' => 20,
                'is_active' => true,
                'stacking_rule' => 'exclusive',
                'promotion_intent' => 'urgency',
                'display_placements' => ['home', 'category', 'product'],
            ], [
                ['type' => 'product', 'id' => $product->id],
            ]);
        }

        // 5) Acquisition promo (first order only) - optional.
        $createPromotion([
            'name' => 'Seeded: Welcome Offer - First order only',
            'description' => 'New customers save on their first order.',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 10,
            'start_at' => $now->subDay(),
            'end_at' => $now->addDays(30),
            'priority' => 8,
            'is_active' => true,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'acquisition',
            'display_placements' => ['home', 'cart', 'checkout'],
        ], [], [
            ['type' => 'first_order_only', 'value' => 1],
            ['type' => 'max_discount', 'value' => 12],
        ]);

        // 6) Sitewide percentage, unconditional, combinable (price override eligible).
        $createPromotion([
            'name' => 'Seeded: Sitewide Save 7%',
            'description' => 'Automatic savings on all items.',
            'type' => 'auto_discount',
            'value_type' => 'percentage',
            'value' => 7,
            'start_at' => $now->subHours(2),
            'end_at' => $now->addDays(5),
            'priority' => 7,
            'is_active' => true,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'cart_growth',
            'display_placements' => null,
        ]);

        // 7) Sitewide fixed, exclusive (non-urgency exclusive path).
        $createPromotion([
            'name' => 'Seeded: Exclusive $5 Off',
            'description' => 'Exclusive discount cannot stack.',
            'type' => 'auto_discount',
            'value_type' => 'fixed',
            'value' => 5,
            'start_at' => $now->subHours(1),
            'end_at' => $now->addDays(3),
            'priority' => 6,
            'is_active' => true,
            'stacking_rule' => 'exclusive',
            'promotion_intent' => 'cart_growth',
            'display_placements' => ['cart', 'checkout'],
        ]);

        // 8) Free shipping value_type (display-only + shipping rules).
        $createPromotion([
            'name' => 'Seeded: Free Shipping Weekend',
            'description' => 'Free shipping during the weekend.',
            'type' => 'auto_discount',
            'value_type' => 'free_shipping',
            'value' => 0,
            'start_at' => $now->subDay(),
            'end_at' => $now->addDays(2),
            'priority' => 5,
            'is_active' => true,
            'stacking_rule' => 'combinable',
            'promotion_intent' => 'shipping_support',
            'display_placements' => ['home', 'cart', 'checkout'],
        ]);

        // 9) Targeted product + category in one promo (mixed targets).
        $mixedCategory = $topCategories->first();
        $mixedProduct = $products->first();
        if ($mixedCategory && $mixedProduct) {
            $createPromotion([
                'name' => 'Seeded: Mixed Target Offer',
                'description' => 'Applies to select product or category.',
                'type' => 'auto_discount',
                'value_type' => 'percentage',
                'value' => 9,
                'start_at' => $now->subHours(2),
                'end_at' => $now->addDays(6),
                'priority' => 4,
                'is_active' => true,
                'stacking_rule' => 'combinable',
                'promotion_intent' => 'cart_growth',
                'display_placements' => ['product', 'category'],
            ], [
                ['type' => 'category', 'id' => $mixedCategory->id],
                ['type' => 'product', 'id' => $mixedProduct->id],
            ]);
        }

        // 10) Short-window flash promo (< 5 minutes) for timing edge case.
        $shortProduct = $products->skip(1)->first() ?? $products->first();
        if ($shortProduct) {
            $createPromotion([
                'name' => 'Seeded: 4-minute Flash',
                'description' => 'Very short promotion window.',
                'type' => 'flash_sale',
                'value_type' => 'percentage',
                'value' => 15,
                'start_at' => $now->subMinutes(1),
                'end_at' => $now->addMinutes(3),
                'priority' => 25,
                'is_active' => true,
                'stacking_rule' => 'exclusive',
                'promotion_intent' => 'urgency',
                'display_placements' => ['home', 'product'],
            ], [
                ['type' => 'product', 'id' => $shortProduct->id],
            ]);
        }

        // 11) Future promo (not yet active).
        $futureCategory = $categories->skip(1)->first() ?? $categories->first();
        if ($futureCategory) {
            $createPromotion([
                'name' => 'Seeded: Upcoming Category Deal',
                'description' => 'Scheduled to start soon.',
                'type' => 'auto_discount',
                'value_type' => 'percentage',
                'value' => 8,
                'start_at' => $now->addDays(2),
                'end_at' => $now->addDays(6),
                'priority' => 3,
                'is_active' => true,
                'stacking_rule' => 'combinable',
                'promotion_intent' => 'urgency',
                'display_placements' => ['category', 'product'],
            ], [
                ['type' => 'category', 'id' => $futureCategory->id],
            ]);
        }

        // 12) Expired promo (should not display/apply).
        $expiredProduct = $products->skip(2)->first() ?? $products->first();
        if ($expiredProduct) {
            $createPromotion([
                'name' => 'Seeded: Expired Deal',
                'description' => 'This promotion has ended.',
                'type' => 'flash_sale',
                'value_type' => 'percentage',
                'value' => 20,
                'start_at' => $now->subDays(5),
                'end_at' => $now->subDays(2),
                'priority' => 2,
                'is_active' => true,
                'stacking_rule' => 'exclusive',
                'promotion_intent' => 'urgency',
                'display_placements' => ['home', 'product'],
            ], [
                ['type' => 'product', 'id' => $expiredProduct->id],
            ]);
        }

        // 13) Inactive promo (is_active=false).
        $inactiveCategory = $categories->skip(2)->first() ?? $categories->first();
        if ($inactiveCategory) {
            $createPromotion([
                'name' => 'Seeded: Inactive Promo',
                'description' => 'Disabled promo should not show.',
                'type' => 'auto_discount',
                'value_type' => 'fixed',
                'value' => 5,
                'start_at' => $now->subDays(1),
                'end_at' => $now->addDays(5),
                'priority' => 1,
                'is_active' => false,
                'stacking_rule' => 'combinable',
                'promotion_intent' => 'other',
                'display_placements' => ['home', 'category'],
            ], [
                ['type' => 'category', 'id' => $inactiveCategory->id],
            ]);
        }
    }
}
