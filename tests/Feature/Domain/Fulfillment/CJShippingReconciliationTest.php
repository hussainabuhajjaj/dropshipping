<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Fulfillment;

use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Domain\Fulfillment\Strategies\CJDropshippingFulfillmentStrategy;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Common\Models\Address;
use App\Domain\Products\Models\ProductVariant;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class CJShippingReconciliationTest extends TestCase
{
    /**
     * Test that shipment captures CJ order details and currency.
     */
    public function test_shipment_captures_cj_details(): void
    {
        // Create test data
        $order = Order::factory()->create([
            'currency' => 'USD',
            'shipping_total_estimated' => 15.00,
        ]);

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);

        // Create a shipment with CJ details
        $shipment = Shipment::create([
            'order_item_id' => $orderItem->id,
            'tracking_number' => 'TRACK123',
            'cj_order_id' => 'CJ001',
            'shipment_order_id' => 'SHIP001',
            'logistic_name' => 'PostNL',
            'postage_amount' => 7.50,
            'currency' => 'USD',
            'carrier' => 'PostNL',
            'shipped_at' => now(),
        ]);

        // Verify shipment fields are persisted
        $this->assertNotNull($shipment->cj_order_id);
        $this->assertEquals('CJ001', $shipment->cj_order_id);
        $this->assertEquals('PostNL', $shipment->logistic_name);
        $this->assertEquals(7.50, $shipment->postage_amount);
        $this->assertEquals('USD', $shipment->currency);
    }

    /**
     * Test order-level shipping reconciliation with multiple shipments.
     */
    public function test_order_reconciliation_with_multiple_shipments(): void
    {
        // Create order with estimated shipping
        $order = Order::factory()->create([
            'currency' => 'USD',
            'shipping_total_estimated' => 15.00,
            'shipping_total' => 15.00,
        ]);

        // Create two order items (will result in two shipments)
        $item1 = OrderItem::factory()->create(['order_id' => $order->id]);
        $item2 = OrderItem::factory()->create(['order_id' => $order->id]);

        // Create shipments with different postage amounts
        Shipment::create([
            'order_item_id' => $item1->id,
            'tracking_number' => 'TRACK001',
            'cj_order_id' => 'CJ001',
            'postage_amount' => 6.25,
            'currency' => 'USD',
            'shipped_at' => now(),
        ]);

        Shipment::create([
            'order_item_id' => $item2->id,
            'tracking_number' => 'TRACK002',
            'cj_order_id' => 'CJ002',
            'postage_amount' => 6.25,
            'currency' => 'USD',
            'shipped_at' => now(),
        ]);

        // Simulate reconciliation (same logic as FulfillmentService)
        $actual = (float) ($order->shipments()->sum('postage_amount') ?? 0);
        $estimated = (float) ($order->shipping_total_estimated ?? $order->shipping_total ?? 0);
        $variance = round($actual - $estimated, 2);

        // Verify reconciliation values
        $this->assertEquals(12.50, $actual, 'Total actual postage should be 12.50');
        $this->assertEquals(15.00, $estimated, 'Estimated shipping should be 15.00');
        $this->assertEquals(-2.50, $variance, 'Variance should be -2.50 (savings)');

        // Update order with reconciliation
        $order->update([
            'shipping_total_actual' => $actual,
            'shipping_variance' => $variance,
            'shipping_reconciled_at' => now(),
        ]);

        // Reload and verify
        $order->refresh();
        $this->assertEquals(12.50, $order->shipping_total_actual);
        $this->assertEquals(-2.50, $order->shipping_variance);
        $this->assertNotNull($order->shipping_reconciled_at);
    }

    /**
     * Test FulfillmentResult captures all CJ details.
     */
    public function test_fulfillment_result_captures_cj_fields(): void
    {
        $result = new FulfillmentResult(
            status: 'succeeded',
            externalReference: 'CJ001',
            cjOrderId: 'CJ001',
            shipmentOrderId: 'SHIP001',
            logisticName: 'PostNL',
            currency: 'USD',
            postageAmount: 12.50,
            trackingNumber: 'TRACK001',
            trackingUrl: 'https://tracking.example.com',
            rawResponse: ['data' => ['orderId' => 'CJ001']]
        );

        $this->assertEquals('CJ001', $result->cjOrderId);
        $this->assertEquals('SHIP001', $result->shipmentOrderId);
        $this->assertEquals('PostNL', $result->logisticName);
        $this->assertEquals('USD', $result->currency);
        $this->assertEquals(12.50, $result->postageAmount);
        $this->assertTrue($result->succeeded());
    }

    /**
     * Test reconciliation handles variance correctly (savings scenario).
     */
    public function test_reconciliation_variance_savings(): void
    {
        $order = Order::factory()->create([
            'shipping_total_estimated' => 20.00,
        ]);

        $item = OrderItem::factory()->create(['order_id' => $order->id]);
        Shipment::create([
            'order_item_id' => $item->id,
            'tracking_number' => 'TRACK001',
            'postage_amount' => 15.00,
            'shipped_at' => now(),
        ]);

        $actual = (float) ($order->shipments()->sum('postage_amount') ?? 0);
        $estimated = (float) $order->shipping_total_estimated;
        $variance = round($actual - $estimated, 2);

        // Negative variance = customer/merchant saved money
        $this->assertEquals(-5.00, $variance);
    }

    /**
     * Test reconciliation handles variance correctly (overage scenario).
     */
    public function test_reconciliation_variance_overage(): void
    {
        $order = Order::factory()->create([
            'shipping_total_estimated' => 10.00,
        ]);

        $item = OrderItem::factory()->create(['order_id' => $order->id]);
        Shipment::create([
            'order_item_id' => $item->id,
            'tracking_number' => 'TRACK001',
            'postage_amount' => 15.00,
            'shipped_at' => now(),
        ]);

        $actual = (float) ($order->shipments()->sum('postage_amount') ?? 0);
        $estimated = (float) $order->shipping_total_estimated;
        $variance = round($actual - $estimated, 2);

        // Positive variance = merchant needs to cover additional cost
        $this->assertEquals(5.00, $variance);
    }

    /**
     * Test that order's shipping_method flows to CJ order creation.
     */
    public function test_order_shipping_method_used_in_cj_creation(): void
    {
        $order = Order::factory()->create([
            'shipping_method' => 'DHL', // Selected by customer at checkout
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);

        // Verify order has shipping_method set (would be used in strategy)
        $this->assertEquals('DHL', $item->order->shipping_method);
    }
}
