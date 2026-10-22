<?php

namespace Tests\Feature\Domain\Orders;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Refund;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Services\RefundService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private RefundService $refundService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refundService = app(RefundService::class);
    }

    /** @test */
    public function can_create_refund_for_pre_shipped_order()
    {
        $order = $this->createOrder();
        $orderItem = $order->orderItems()->first();
        $shipment = $orderItem->shipments()->create([
            'tracking_number' => null,
            'shipped_at' => null, // Pre-shipped
        ]);

        $refund = $this->refundService->createRefund([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'shipment_id' => $shipment->id,
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'customer_reason' => 'Change of mind',
        ]);

        $this->assertNotNull($refund->id);
        $this->assertEquals(Refund::STATUS_PENDING, $refund->status);
        $this->assertEquals(50.00, $refund->amount);
    }

    /** @test */
    public function can_create_refund_for_shipped_order_with_any_reason()
    {
        $order = $this->createOrder();
        $orderItem = $order->orderItems()->first();
        $shipment = $orderItem->shipments()->create([
            'tracking_number' => 'TRACK123',
            'shipped_at' => now(),
            'delivered_at' => null,
        ]);

        $refund = $this->refundService->createRefund([
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
        ]);

        $this->assertNotNull($refund->id);
        $this->assertEquals(Refund::STATUS_PENDING, $refund->status);
    }

    /** @test */
    public function can_create_refund_for_delivered_order_with_valid_reasons_only()
    {
        $order = $this->createOrder();
        $orderItem = $order->orderItems()->first();
        $shipment = $orderItem->shipments()->create([
            'tracking_number' => 'TRACK123',
            'shipped_at' => now()->subDays(5),
            'delivered_at' => now(),
        ]);

        // Valid reasons for delivered
        foreach ([Refund::REASON_DAMAGED, Refund::REASON_WRONG_ITEM, Refund::REASON_QUALITY_ISSUE, Refund::REASON_LATE_DELIVERY] as $reason) {
            $refund = $this->refundService->createRefund([
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'amount' => 50.00,
                'reason_code' => $reason,
            ]);

            $this->assertNotNull($refund->id);
        }
    }

    /** @test */
    public function cannot_create_refund_for_delivered_order_with_invalid_reason()
    {
        $order = $this->createOrder();
        $orderItem = $order->orderItems()->first();
        $shipment = $orderItem->shipments()->create([
            'tracking_number' => 'TRACK123',
            'shipped_at' => now()->subDays(5),
            'delivered_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refunds for delivered shipments require a specific reason');

        $this->refundService->createRefund([
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
        ]);
    }

    /** @test */
    public function cannot_create_refund_with_invalid_amount()
    {
        $order = $this->createOrder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund amount must be greater than zero');

        $this->refundService->createRefund([
            'order_id' => $order->id,
            'amount' => 0,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
        ]);
    }

    /** @test */
    public function can_approve_pending_refund()
    {
        $order = $this->createOrder();
        $refund = $order->refunds()->create([
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_PENDING,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $approved = $this->refundService->approveRefund($refund, $user);

        $this->assertEquals(Refund::STATUS_APPROVED, $approved->status);
        $this->assertEquals($user->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    /** @test */
    public function can_reject_pending_refund()
    {
        $order = $this->createOrder();
        $refund = $order->refunds()->create([
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_PENDING,
        ]);

        $rejected = $this->refundService->rejectRefund($refund, 'Duplicate order');

        $this->assertEquals(Refund::STATUS_REJECTED, $rejected->status);
    }

    /** @test */
    public function can_complete_approved_refund()
    {
        $order = $this->createOrder();
        $refund = $order->refunds()->create([
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_APPROVED,
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);

        $completed = $this->refundService->completeRefund($refund, 'TXN-12345', ['status' => 'success']);

        $this->assertEquals(Refund::STATUS_COMPLETED, $completed->status);
        $this->assertEquals('TXN-12345', $completed->transaction_id);
        $this->assertNotNull($completed->processed_at);
    }

    /** @test */
    public function can_cancel_pending_or_approved_refund()
    {
        $order = $this->createOrder();

        // Cancel pending
        $pending = $order->refunds()->create([
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_PENDING,
        ]);

        $cancelled = $this->refundService->cancelRefund($pending, 'User changed mind');
        $this->assertEquals(Refund::STATUS_CANCELLED, $cancelled->status);

        // Cancel approved
        $approved = $order->refunds()->create([
            'amount' => 30.00,
            'reason_code' => Refund::REASON_DAMAGED,
            'status' => Refund::STATUS_APPROVED,
            'approved_by' => User::factory()->create()->id,
            'approved_at' => now(),
        ]);

        $cancelled2 = $this->refundService->cancelRefund($approved);
        $this->assertEquals(Refund::STATUS_CANCELLED, $cancelled2->status);
    }

    /** @test */
    public function can_calculate_total_refunded_amount()
    {
        $order = $this->createOrder();

        $order->refunds()->create([
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_COMPLETED,
        ]);

        $order->refunds()->create([
            'amount' => 30.00,
            'reason_code' => Refund::REASON_DAMAGED,
            'status' => Refund::STATUS_COMPLETED,
        ]);

        $order->refunds()->create([
            'amount' => 20.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_PENDING,
        ]);

        $totalCompleted = $this->refundService->getOrderRefundTotal($order, Refund::STATUS_COMPLETED);
        $this->assertEquals(80.00, $totalCompleted);

        $totalAll = $this->refundService->getOrderRefundTotal($order);
        $this->assertEquals(100.00, $totalAll);
    }

    /** @test */
    public function can_calculate_remaining_refundable_amount()
    {
        $order = $this->createOrder(grand_total: 200.00);

        $order->refunds()->create([
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
            'status' => Refund::STATUS_COMPLETED,
        ]);

        $remaining = $this->refundService->getRemainingRefundable($order);
        $this->assertEquals(150.00, $remaining);
    }

    /** @test */
    public function supports_multiple_refunds_per_order()
    {
        $order = $this->createOrder();

        $refund1 = $this->refundService->createRefund([
            'order_id' => $order->id,
            'amount' => 25.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
        ]);

        $refund2 = $this->refundService->createRefund([
            'order_id' => $order->id,
            'amount' => 15.00,
            'reason_code' => Refund::REASON_DAMAGED,
        ]);

        $refunds = $this->refundService->getRefunds($order);
        $this->assertCount(2, $refunds);
    }

    /** @test */
    public function creates_audit_log_on_refund_actions()
    {
        $order = $this->createOrder();
        $user = User::factory()->create();

        $refund = $this->refundService->createRefund([
            'order_id' => $order->id,
            'amount' => 50.00,
            'reason_code' => Refund::REASON_CUSTOMER_REQUEST,
        ]);

        $auditLogs = $order->auditLogs()->where('action', 'refund_created')->get();
        $this->assertCount(1, $auditLogs);
        $this->assertStringContainsString((string) $refund->id, json_encode($auditLogs->first()->details));
    }

    // ==================== Helper Methods ====================

    private function createOrder($grand_total = 150.00): Order
    {
        $order = Order::factory()->create([
            'grand_total' => $grand_total,
        ]);

        $orderItem = $order->orderItems()->create([
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => $grand_total,
        ]);

        $orderItem->shipments()->create([
            'tracking_number' => null,
        ]);

        return $order;
    }
}
