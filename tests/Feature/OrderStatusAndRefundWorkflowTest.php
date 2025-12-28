<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Orders\Models\Order;
use App\Enums\RefundReasonEnum;
use App\Models\Customer;
use App\Notifications\OrderStatusChanged;
use App\Notifications\RefundApproved;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusAndRefundWorkflowTest extends TestCase
{
    public function test_order_customer_status_label_and_explanation()
    {
        $order = Order::factory()->create([
            'customer_status' => 'in_transit',
        ]);

        $this->assertEquals('In transit', $order->getCustomerStatusLabel());
        $this->assertStringContainsString('traveling', $order->getCustomerStatusExplanation());
    }

    public function test_can_refund_checks_order_state()
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $this->assertTrue($order->canBeRefunded());

        $refundedOrder = Order::factory()->create(['status' => 'refunded']);
        $this->assertFalse($refundedOrder->canBeRefunded());
    }

    public function test_mark_refunded_updates_order_and_sends_notification()
    {
        Notification::fake();

        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'grand_total' => 5000, // $50.00
            'status' => 'pending',
        ]);

        $order->markRefunded(
            RefundReasonEnum::CUSTOMER_DISSATISFIED,
            4250, // 85% refund
            'Customer requested refund'
        );

        $this->assertEquals('refunded', $order->fresh()->customer_status);
        $this->assertEquals(4250, $order->fresh()->refund_amount);
        $this->assertEquals(RefundReasonEnum::CUSTOMER_DISSATISFIED, $order->fresh()->refund_reason);
        $this->assertNotNull($order->fresh()->refunded_at);

        Notification::assertSentTo(
            [$customer],
            RefundApproved::class
        );
    }

    public function test_update_customer_status_sends_notification()
    {
        Notification::fake();

        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_status' => 'received',
        ]);

        $order->updateCustomerStatus('dispatched');

        $this->assertEquals('dispatched', $order->fresh()->customer_status);

        Notification::assertSentTo(
            [$customer],
            OrderStatusChanged::class
        );
    }

    public function test_refund_reason_enum_values()
    {
        $reason = RefundReasonEnum::SUPPLIER_UNABLE_TO_FULFILL;
        $this->assertEquals(100, $reason->refundPercentage());

        $reason = RefundReasonEnum::CUSTOMER_DISSATISFIED;
        $this->assertEquals(85, $reason->refundPercentage());

        $this->assertNotEmpty($reason->label());
        $this->assertNotEmpty($reason->description());
    }

    public function test_order_status_card_component_accepts_all_statuses()
    {
        $statuses = ['received', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'issue_detected', 'refunded', 'cancelled'];

        foreach ($statuses as $status) {
            $order = Order::factory()->create(['customer_status' => $status]);
            $label = $order->getCustomerStatusLabel();
            $explanation = $order->getCustomerStatusExplanation();

            $this->assertNotEmpty($label, "No label for status: $status");
            $this->assertNotEmpty($explanation, "No explanation for status: $status");
        }
    }
}
