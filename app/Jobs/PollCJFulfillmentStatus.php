<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Services\LinehaulShipmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class PollCJFulfillmentStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $fulfillmentJobId)
    {
    }

    public function handle(CJDropshippingClient $client): void
    {
        $job = FulfillmentJob::with(['orderItem.order', 'order', 'provider'])
            ->find($this->fulfillmentJobId);

        if (! $job || ! $job->external_reference) {
            return;
        }

        // Ensure this job is for CJ strategy
        if (! $job->provider || $job->provider->driver_class !== \App\Domain\Fulfillment\Strategies\CJDropshippingFulfillmentStrategy::class) {
            return;
        }

        $response = $client->orderStatus(['orderIds' => [$job->external_reference]]);
//        dd($response);
        $body = is_array($response) ? $response : (isset($response->data) ? $response->data : []);
        $data = Arr::get($body, '0');

        if (! $data) {
            return;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $trackingNumber = $data['trackingNumber'] ?? null;
        $trackingUrl = $data['trackingUrl'] ?? null;

        $job->status = match ($status) {
            'completed', 'success', 'fulfilled' => 'succeeded',
            'failed', 'cancelled' => 'failed',
            default => $job->status,
        };
        $job->external_reference = $job->external_reference ?? ($data['orderId'] ?? null);
        $job->last_error = $data['errorMsg'] ?? $job->last_error;
        $job->fulfilled_at = $job->status === 'succeeded' ? now() : $job->fulfilled_at;
        $job->save();

        $orderItemIds = $job->orderItemIds();
        $orderItems = OrderItem::query()->whereIn('id', $orderItemIds)->get()->keyBy('id');
        $order = $job->order ?? $job->orderItem?->order;

        if ($order && (is_array($data) && $data !== [])) {
            app(LinehaulShipmentService::class)->createOrUpdateFromCjOrder($order, $data);
        }

        if ($trackingNumber || $job->external_reference || ! empty($data['shipmentOrderId'] ?? null)) {

            if ($job->order_item_id || count($orderItemIds) <= 1) {
                $orderItemId = $job->order_item_id ?: $orderItemIds[0] ?? null;
                $orderItem = $orderItemId ? $orderItems->get($orderItemId) : null;

                if ($orderItem) {
                    Shipment::updateOrCreate(
                        Shipment::matchAttributesForOrderItem(
                            $orderItem,
                            $trackingNumber,
                            $data['shipmentOrderId'] ?? null,
                            $job->external_reference
                        ),
                        [
                            'order_id' => $orderItem->order_id,
                            'tracking_number' => $trackingNumber,
                            'carrier' => $data['carrier'] ?? null,
                            'tracking_url' => $trackingUrl,
                            'cj_order_id' => $job->external_reference,
                            'shipment_order_id' => $data['shipmentOrderId'] ?? null,
                            'logistic_name' => $data['logisticName'] ?? null,
                            'postage_amount' => is_numeric($data['postageAmount'] ?? null) ? (float) $data['postageAmount'] : null,
                            'currency' => $orderItem->order?->currency ?? $order?->currency,
                            'shipped_at' => $data['shippedAt'] ?? now(),
                            'raw_events' => $data['events'] ?? null,
                        ]
                    );
                }
            } elseif ($order) {
                $shipment = Shipment::updateOrCreate(
                    Shipment::matchAttributesForOrder(
                        $order,
                        $trackingNumber,
                        $data['shipmentOrderId'] ?? null,
                        $job->external_reference
                    ),
                    [
                        'tracking_number' => $trackingNumber,
                        'carrier' => $data['carrier'] ?? null,
                        'tracking_url' => $trackingUrl,
                        'cj_order_id' => $job->external_reference,
                        'shipment_order_id' => $data['shipmentOrderId'] ?? null,
                        'logistic_name' => $data['logisticName'] ?? null,
                        'postage_amount' => is_numeric($data['postageAmount'] ?? null) ? (float) $data['postageAmount'] : null,
                        'currency' => $order->currency,
                        'shipped_at' => $data['shippedAt'] ?? now(),
                        'raw_events' => $data['events'] ?? null,
                    ]
                );

                $shipment->items()->delete();
                foreach ($orderItemIds as $orderItemId) {
                    if ($orderItems->has($orderItemId)) {
                        $shipment->items()->create(['order_item_id' => $orderItemId]);
                    }
                }
            }

            if ($order) {
                $actual = (float) ($order->shipments()->sum('postage_amount') ?? 0);
                $estimated = (float) ($order->shipping_total_estimated ?? $order->shipping_total ?? 0);
                $order->update([
                    'shipping_total_actual' => $actual,
                    'shipping_variance' => round($actual - $estimated, 2),
                    'shipping_reconciled_at' => now(),
                ]);

                app(\App\Domain\Orders\Services\OrderCostBreakdownService::class)->recalculate($order);
            }
        }
    }
}
