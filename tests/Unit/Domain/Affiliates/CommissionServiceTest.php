<?php

namespace App\Domain\Affiliates\Tests\Unit;

use App\Domain\Affiliates\Models\Affiliate;
use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Domain\Affiliates\Services\AffiliateCommissionService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Models\Coupon;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    public function test_affiliate_commission_service_creates_commission(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => 100]);

        $service = new AffiliateCommissionService();
        $commission = $service->createCommission($order, $orderItem, $affiliate);

        $this->assertEquals($affiliate->id, $commission->affiliate_id);
        $this->assertEquals($order->id, $commission->order_id);
        $this->assertEquals($orderItem->id, $commission->order_item_id);
        $this->assertEquals(10.00, (float) $commission->commission_amount);
        $this->assertEquals('pending', $commission->status);
    }

    public function test_affiliate_commission_calculates_from_rate(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.25]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => 200]);

        $service = new AffiliateCommissionService();
        $commission = $service->createCommission($order, $orderItem, $affiliate);

        $this->assertEquals(50.00, (float) $commission->commission_amount);
        $this->assertEquals(0.25, (float) $commission->commission_rate);
    }

    public function test_affiliate_commission_can_be_approved(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10, 'balance_pending' => 0, 'balance_available' => 0, 'total_earned' => 0]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => 100]);

        $service = new AffiliateCommissionService();
        $commission = $service->createCommission($order, $orderItem, $affiliate);

        $service->approveCommission($commission);

        $this->assertEquals('approved', $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->approved_at);
        $this->assertEquals(10.00, (float) $affiliate->fresh()->balance_available);
    }

    public function test_duplicate_commissions_are_prevented(): void
    {
        $affiliate = Affiliate::factory()->create(['commission_rate' => 0.10]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => 100]);

        $service = new AffiliateCommissionService();
        $service->createCommission($order, $orderItem, $affiliate);

        $this->assertDatabaseCount('affiliate_commissions', 1);
    }

    public function test_referral_tracking_creates_referral_record(): void
    {
        $affiliate = Affiliate::factory()->create(['status' => 'approved']);
        $referral = AffiliateReferral::create([
            'affiliate_id' => $affiliate->id,
            'visitor_token' => 'test-token-' . uniqid(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertDatabaseHas('affiliate_referrals', [
            'id' => $referral->id,
            'affiliate_id' => $affiliate->id,
        ]);
    }
}
