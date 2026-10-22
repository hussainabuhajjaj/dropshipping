<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Fulfillment\Models\FulfillmentAttempt;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Services\LinehaulShipmentService;
use Illuminate\Console\Command;

class BackfillLogisticsRecords extends Command
{
    protected $signature = 'logistics:backfill-records {--dry-run : Preview without persisting changes}';

    protected $description = 'Backfill shipments and linehaul shipments from successful fulfillment attempts.';

    public function handle(LinehaulShipmentService $linehaulShipments): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $shipmentCount = 0;
        $linehaulCount = 0;

        FulfillmentAttempt::query()
            ->where('status', 'success')
            ->with(['fulfillmentJob.order', 'fulfillmentJob.orderItem.order'])
            ->orderBy('id')
            ->chunkById(100, function ($attempts) use ($dryRun, $linehaulShipments, &$shipmentCount, &$linehaulCount): void {
                foreach ($attempts as $attempt) {
                    $payload = is_array($attempt->response_payload) ? $attempt->response_payload : [];
                    if ($payload === []) {
                        continue;
                    }

                    $job = $attempt->fulfillmentJob;
                    $order = $job?->order ?? $job?->orderItem?->order;
                    $orderItem = $job?->orderItem;

                    if ($order) {
                        if (! $dryRun) {
                            $linehaulShipments->createOrUpdateFromCjOrder($order, $payload);
                        }
                        $linehaulCount++;
                    }

                    $trackingNumber = $payload['trackingNumber'] ?? $payload['trackNumber'] ?? null;
                    $shipmentOrderId = $payload['shipmentOrderId'] ?? null;
                    $cjOrderId = $payload['orderId'] ?? $job?->external_reference;

                    if (! $orderItem && $job) {
                        $orderItemId = $job->orderItemIds()[0] ?? null;
                        $orderItem = $orderItemId ? OrderItem::query()->find($orderItemId) : null;
                    }

                    if ($orderItem) {
                        if (! $dryRun) {
                            Shipment::query()->updateOrCreate(
                                Shipment::matchAttributesForOrderItem($orderItem, $trackingNumber, $shipmentOrderId, $cjOrderId),
                                [
                                    'order_id' => $orderItem->order_id,
                                    'tracking_number' => $trackingNumber,
                                    'tracking_url' => $payload['trackingUrl'] ?? null,
                                    'carrier' => $payload['carrier'] ?? null,
                                    'logistic_name' => $payload['logisticName'] ?? null,
                                    'cj_order_id' => $cjOrderId,
                                    'shipment_order_id' => $shipmentOrderId,
                                    'postage_amount' => is_numeric($payload['postageAmount'] ?? null) ? (float) $payload['postageAmount'] : null,
                                    'currency' => $orderItem->order?->currency,
                                    'shipped_at' => $payload['shippedAt'] ?? $attempt->created_at,
                                    'raw_events' => $payload['events'] ?? null,
                                ]
                            );
                        }
                        $shipmentCount++;
                        continue;
                    }

                    if ($order) {
                        if (! $dryRun) {
                            $shipment = Shipment::query()->updateOrCreate(
                                Shipment::matchAttributesForOrder($order, $trackingNumber, $shipmentOrderId, $cjOrderId),
                                [
                                    'tracking_number' => $trackingNumber,
                                    'tracking_url' => $payload['trackingUrl'] ?? null,
                                    'carrier' => $payload['carrier'] ?? null,
                                    'logistic_name' => $payload['logisticName'] ?? null,
                                    'cj_order_id' => $cjOrderId,
                                    'shipment_order_id' => $shipmentOrderId,
                                    'postage_amount' => is_numeric($payload['postageAmount'] ?? null) ? (float) $payload['postageAmount'] : null,
                                    'currency' => $order->currency,
                                    'shipped_at' => $payload['shippedAt'] ?? $attempt->created_at,
                                    'raw_events' => $payload['events'] ?? null,
                                ]
                            );

                            $shipment->items()->delete();
                            foreach ($job?->orderItemIds() ?? [] as $itemId) {
                                $shipment->items()->firstOrCreate(['order_item_id' => $itemId]);
                            }
                        }
                        $shipmentCount++;
                    }
                }
            });

        $this->info(sprintf(
            '%sLinehaul rows processed: %d | Shipment rows processed: %d',
            $dryRun ? '[dry-run] ' : '',
            $linehaulCount,
            $shipmentCount
        ));

        return self::SUCCESS;
    }
}
