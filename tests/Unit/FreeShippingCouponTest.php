<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeShippingCouponTest extends TestCase
{
    use RefreshDatabase;

    private function freeShippingCoupon(): Coupon
    {
        return Coupon::create([
            'code' => 'LUCKYFS1',
            'description' => 'Free shipping reward',
            'type' => 'free_shipping',
            'amount' => 0,
            'is_active' => true,
        ]);
    }

    public function test_free_shipping_coupon_is_detected(): void
    {
        $this->assertTrue($this->freeShippingCoupon()->isFreeShipping());
    }

    public function test_calculate_discounts_preserves_free_shipping_flag_and_zero_amount(): void
    {
        $coupon = $this->freeShippingCoupon();
        $session = $this->app['session'];

        $session->put('cart_coupon', ['code' => $coupon->code]);
        $session->save();

        $result = calculateDiscounts([], [], ['code' => $coupon->code], null, 40000.0);

        $this->assertTrue($result['free_shipping'] ?? false);
        $this->assertEquals(0.0, (float) $result['amount']);
    }

    public function test_apply_shipping_rules_zeroes_shipping_when_free_shipping(): void
    {
        $shipping = applyShippingRules(1500.0, 40000.0, 0.0, null, true);

        $this->assertEquals(0.0, $shipping);
    }

    public function test_apply_shipping_rules_keeps_fee_without_free_shipping(): void
    {
        $shipping = applyShippingRules(1500.0, 10000.0, 0.0, null, false);

        $this->assertEquals(1500.0, $shipping);
    }
}
