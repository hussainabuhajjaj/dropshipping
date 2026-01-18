<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\Coupons\CouponValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_category_targeted_coupon_for_matching_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $coupon = Coupon::create([
            'code' => 'CAT10',
            'description' => 'Category discount',
            'type' => 'percent',
            'amount' => 10,
            'is_active' => true,
            'applicable_to' => 'categories',
        ]);
        $coupon->categories()->attach($category->id);

        $lines = [
            ['product_id' => $product->id, 'category_id' => $category->id, 'price' => 10.0],
        ];

        $validator = app(CouponValidator::class);
        $error = $validator->validateForCart($coupon, $lines, 10.0, null);

        $this->assertNull($error);
    }

    public function test_it_rejects_sale_items_when_excluded(): void
    {
        $coupon = Coupon::create([
            'code' => 'NOSALE',
            'description' => 'No sale items',
            'type' => 'percent',
            'amount' => 10,
            'is_active' => true,
            'exclude_on_sale' => true,
        ]);

        $lines = [
            ['product_id' => 1, 'category_id' => 1, 'price' => 10.0, 'compare_at_price' => 20.0],
        ];

        $validator = app(CouponValidator::class);
        $error = $validator->validateForCart($coupon, $lines, 10.0, null);

        $this->assertSame('Coupon cannot be used on sale items.', $error);
    }
}
