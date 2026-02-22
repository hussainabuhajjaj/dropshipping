<?php

namespace App\Domain\Affiliates\Tests\Unit;

use App\Domain\Affiliates\Services\AffiliateCouponService;
use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Coupons\Models\Coupon;
use Tests\TestCase;

class AffiliateCouponServiceTest extends TestCase
{
    public function test_create_affiliate_coupon(): void
    {
        $affiliate = Affiliate::factory()->create();
        $service = new AffiliateCouponService();

        $couponData = [
            'type' => 'percent',
            'amount' => 15,
            'description' => 'Test affiliate coupon',
        ];

        $coupon = $service->createAffiliateCoupon($affiliate, $couponData);

        $this->assertInstanceOf(Coupon::class, $coupon);
        $this->assertEquals($affiliate->id, $coupon->affiliate_id);
        $this->assertTrue($coupon->affects_affiliate_commission);
        $this->assertEquals('percent', $coupon->type);
        $this->assertEquals(15, $coupon->amount);
    }

    public function test_generate_unique_coupon_code(): void
    {
        $affiliate = Affiliate::factory()->create(['referral_code' => 'TEST123']);
        $service = new AffiliateCouponService();

        $code1 = $service->generateUniqueCouponCode($affiliate);
        $code2 = $service->generateUniqueCouponCode($affiliate);

        $this->assertNotEquals($code1, $code2);
        $this->assertStringStartsWith('TEST123', $code1);
        $this->assertStringStartsWith('TEST123', $code2);
        $this->assertEquals(11, strlen($code1)); // TEST123 + 3 digits
        $this->assertEquals(11, strlen($code2));
    }

    public function test_create_standard_affiliate_coupon(): void
    {
        $affiliate = Affiliate::factory()->create();
        $service = new AffiliateCouponService();

        $coupon = $service->createStandardAffiliateCoupon($affiliate, 20, 100);

        $this->assertEquals('percent', $coupon->type);
        $this->assertEquals(20, $coupon->amount);
        $this->assertEquals($affiliate->id, $coupon->affiliate_id);
        $this->assertTrue($coupon->affects_affiliate_commission);
        $this->assertEquals(100, $coupon->max_uses);
        $this->assertNotNull($coupon->starts_at);
        $this->assertNotNull($coupon->ends_at);
    }

    public function test_validate_affiliate_coupon_assignment(): void
    {
        $affiliate1 = Affiliate::factory()->create();
        $affiliate2 = Affiliate::factory()->create();
        $service = new AffiliateCouponService();

        $coupon = Coupon::factory()->create(['affiliate_id' => $affiliate1->id]);

        // Valid assignment
        $this->assertTrue($service->validateAffiliateCouponAssignment($coupon, $affiliate1));

        // Invalid assignment - different affiliate
        $this->assertFalse($service->validateAffiliateCouponAssignment($coupon, $affiliate2));

        // Invalid assignment - inactive affiliate
        $affiliate1->update(['is_active' => false]);
        $this->assertFalse($service->validateAffiliateCouponAssignment($coupon, $affiliate1));
    }
}
