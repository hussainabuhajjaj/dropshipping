<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Models\TrackingEvent;
use App\Domain\Observability\EventLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrackingService
{
    public function __construct(private readonly EventLogger $logger)
    {
    }

    /**
     * Create or update a shipment for an order item.
     */
    public function recordShipment(OrderItem $item, array $data): Shipment
    {
        return DB::transaction(function () use ($item, $data) {
            $shipment = Shipment::updateOrCreate(
                [
                    'order_item_id' => $item->id,
                    'tracking_number' => $data['tracking_number'],
                ],
                [
                    'carrier' => $data['carrier'] ?? null,
                    'tracking_url' => $data['tracking_url'] ?? null,
                    'shipped_at' => $data['shipped_at'] ?? now(),
                    'delivered_at' => $data['delivered_at'] ?? null,
                    'raw_events' => $data['raw_events'] ?? null,
                ]
            );

            $this->logger->fulfillment(
                $item,
                'tracking_number_added',
                'in_transit',
                null,
                $data
            );

            return $shipment;
        });
    }

    /**
     * Append a tracking event, idempotent by external_id when provided.
     */
    public function recordEvent(Shipment $shipment, array $data): TrackingEvent
    {
        if (! isset($data['status_code'], $data['occurred_at'])) {
            throw new RuntimeException('Tracking event requires status_code and occurred_at.');
        }

        $attributes = [
            'shipment_id' => $shipment->id,
            'status_code' => $data['status_code'],
            'occurred_at' => $data['occurred_at'],
        ];

        if (isset($data['external_id'])) {
            $event = TrackingEvent::firstOrNew(['external_id' => $data['external_id']]);
            $event->shipment_id = $shipment->id;
        } else {
            $event = new TrackingEvent($attributes);
        }

        $event->fill([
            'status_label' => $data['status_label'] ?? null,
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'payload' => $data['payload'] ?? null,
        ] + $attributes);

        $event->save();

        if ($this->isDeliveredStatus($event->status_code)) {
            $shipment->delivered_at = $shipment->delivered_at ?? $event->occurred_at;
            $shipment->save();
        }

        $this->logger->fulfillment(
            $shipment->orderItem,
            'tracking_event',
            $event->status_code,
            $event->description,
            $data
        );

        return $event;
    }

    /**
     * Bulk sync from webhook payloads (automated updates).
     */
    public function syncFromWebhook(Shipment $shipment, array $events): void
    {
        foreach ($events as $event) {
            $this->recordEvent($shipment, $event);
        }
    }

    private function isDeliveredStatus(string $status): bool
    {
        return in_array(strtolower($status), ['delivered', 'delivered_local', 'completed'], true);
    }
}
