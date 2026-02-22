<?php

namespace App\Domain\Affiliates\Tests\Unit;

use App\Domain\Affiliates\Services\CommissionService;
use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Coupons\Models\Coupon;
use Tests\TestCase;
use Mockery;

class CommissionServiceTest extends TestCase
{
    public function test_calculate_commissions_with_percent_coupon(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        $coupon = Coupon::factory()->create([
            'type' => 'percent',
            'amount' => 10,
            'affects_affiliate_commission' => true,
        ]);

        $order = Order::factory()->create([
            'coupon_code' => $coupon->code,
            'customer_id' => $affiliate->referrals()->create()->customer_id,
        ]);

        $orderItems = OrderItem::factory()->count(2)->create([
            'order_id' => $order->id,
            'price' => 100,
            'quantity' => 1,
        ]);

        $service = new CommissionService();
        $commissions = $service->calculateCommissions($order);

        $this->assertCount(2, $commissions);
        $this->assertEquals(90.00, $commissions->first()['commission_base_amount']); // 10% discount
        $this->assertEquals(9.00, $commissions->first()['commission_amount']); // 10% of 90
        $this->assertEquals($coupon->code, $commissions->first()['coupon_code']);
    }

    public function test_calculate_commissions_with_fixed_coupon(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        $coupon = Coupon::factory()->create([
            'type' => 'fixed',
            'amount' => 20,
            'affects_affiliate_commission' => true,
        ]);

        $order = Order::factory()->create([
            'coupon_code' => $coupon->code,
            'customer_id' => $affiliate->referrals()->create()->customer_id,
        ]);

        $orderItems = OrderItem::factory()->count(2)->create([
            'order_id' => $order->id,
            'price' => 100,
            'quantity' => 1,
        ]);

        $service = new CommissionService();
        $commissions = $service->calculateCommissions($order);

        $this->assertCount(2, $commissions);
        $this->assertEquals(90.00, $commissions->first()['commission_base_amount']); // $20 discount distributed
        $this->assertEquals(9.00, $commissions->first()['commission_amount']); // 10% of 90
        $this->assertEquals(10.00, $commissions->first()['discount_amount_applied']); // $20/2 items
    }

    public function test_calculate_commissions_with_affiliate_specific_coupon(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.15]);
        $coupon = Coupon::factory()->create([
            'affiliate_id' => $affiliate->id,
            'affects_affiliate_commission' => true,
        ]);

        $order = Order::factory()->create([
            'coupon_code' => $coupon->code,
            'customer_id' => $affiliate->referrals()->create()->customer_id,
        ]);

        $orderItems = OrderItem::factory()->count(1)->create([
            'order_id' => $order->id,
            'price' => 100,
            'quantity' => 1,
        ]);

        $service = new CommissionService();
        $commissions = $service->calculateCommissions($order);

        $this->assertCount(1, $commissions);
        $this->assertEquals($affiliate->id, $commissions->first()['affiliate_id']);
    }

    public function test_calculate_commissions_excludes_shipping_and_tax(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        
        $order = Order::factory()->create([
            'customer_id' => $affiliate->referrals()->create()->customer_id,
            'shipping_amount' => 10,
            'tax_amount' => 8,
        ]);

        $orderItems = OrderItem::factory()->count(1)->create([
            'order_id' => $order->id,
            'price' => 100,
            'quantity' => 1,
        ]);

        $service = new CommissionService();
        $commissions = $service->calculateCommissions($order);

        $this->assertEquals(100.00, $commissions->first()['commission_base_amount']); // Excludes shipping and tax
        $this->assertEquals(10.00, $commissions->first()['commission_amount']); // 10% of 100
    }

    public function test_calculate_commissions_with_no_affiliate_coupon_flag(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        $coupon = Coupon::factory()->create([
            'affects_affiliate_commission' => false,
        ]);

        $order = Order::factory()->create([
            'coupon_code' => $coupon->code,
            'customer_id' => $affiliate->referrals()->create()->customer_id,
        ]);

        $orderItems = OrderItem::factory()->count(1)->create([
            'order_id' => $order->id,
            'price' => 100,
            'quantity' => 1,
        ]);

        $service = new CommissionService();
        $commissions = $service->calculateCommissions($order);

        $this->assertCount(0, $commissions); // No commissions when flag is false
    }

    public function test_create_commissions_idempotent(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        
        $order = Order::factory()->create([
            'customer_id' => $affiliate->referrals()->create()->customer_id,
        ]);

        $orderItems = OrderItem::factory()->count(1)->create([
            'order_id' => $order->id,
            'price' => 100,
            'quantity' => 1,
        ]);

        $service = new CommissionService();
        
        // First call should create commissions
        $commissions1 = $service->createCommissions($order);
        $this->assertCount(1, $commissions1);

        // Second call should not create duplicate commissions
        $commissions2 = $service->createCommissions($order);
        $this->assertCount(0, $commissions2);
    }
}
