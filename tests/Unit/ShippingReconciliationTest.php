<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Fulfillment\Services\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_shipping_reconciliation_sums_postage(): void
    {
        $order = Order::factory()->create([
            'shipping_total' => 12.50,
            'shipping_total_estimated' => 12.50,
        ]);

        $itemA = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1]);
        $itemB = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2]);

        Shipment::create(['order_item_id' => $itemA->id, 'tracking_number' => 'T1', 'postage_amount' => 4.00]);
        Shipment::create(['order_item_id' => $itemB->id, 'tracking_number' => 'T2', 'postage_amount' => 6.00]);

        // Use the FulfillmentService reconciliation helper
        $service = app(FulfillmentService::class);
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('reconcileOrderShipping');
        $method->setAccessible(true);
        $method->invoke($service, $order);

        $order->refresh();

        $this->assertSame(10.00, (float) $order->shipping_total_actual);
        $this->assertSame(-2.50, (float) $order->shipping_variance);
        $this->assertNotNull($order->shipping_reconciled_at);
    }
}
