<?php

namespace App\Domain\Messaging\Services;

use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Shipment;
use App\Enums\MessageTriggerType;
use Illuminate\Support\Collection;

/**
 * Provides trigger recommendations and automatic message scheduling.
 */
class MessageTriggerRecommendationEngine
{
    protected MessageTemplateService $service;

    public function __construct(MessageTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * Get recommended templates when a shipment is delayed.
     */
    public function recommendForDelay(Shipment $shipment, int $daysDelayed = 7): Collection
    {
        return MessageTemplate::query()
            ->where('is_active', true)
            ->whereJsonContains('trigger_types', MessageTriggerType::SHIPMENT_DELAYED->value)
            ->orWhere('type', 'delay')
            ->get();
    }

    /**
     * Get recommended templates for customs issues.
     */
    public function recommendForCustoms(Shipment $shipment, string $reason = ''): Collection
    {
        return MessageTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereJsonContains('trigger_types', MessageTriggerType::CUSTOMS_HOLD->value)
                    ->orWhere('type', 'customs');
            })
            ->get();
    }

    /**
     * Get recommended templates for split shipments.
     */
    public function recommendForSplitShipment(Order $order, int $shipmentCount = 2): Collection
    {
        return MessageTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereJsonContains('trigger_types', MessageTriggerType::SPLIT_SHIPMENT_CREATED->value)
                    ->orWhere('type', 'split_shipments');
            })
            ->get();
    }

    /**
     * Get recommended templates for refund updates.
     */
    public function recommendForRefund(Order $order, string $status = 'initiated'): Collection
    {
        $trigger = match ($status) {
            'initiated' => MessageTriggerType::REFUND_INITIATED,
            'processed' => MessageTriggerType::REFUND_PROCESSED,
            'failed' => MessageTriggerType::REFUND_FAILED,
            default => MessageTriggerType::REFUND_INITIATED,
        };

        return MessageTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($trigger) {
                $query->whereJsonContains('trigger_types', $trigger->value)
                    ->orWhere('type', 'refund_update');
            })
            ->get();
    }

    /**
     * Get recommended templates for delivery updates.
     */
    public function recommendForDeliveryUpdate(Shipment $shipment, string $milestone = 'dispatched'): Collection
    {
        $trigger = match ($milestone) {
            'dispatched' => MessageTriggerType::SHIPMENT_DISPATCHED,
            'out_for_delivery' => MessageTriggerType::SHIPMENT_OUT_FOR_DELIVERY,
            'delivered' => MessageTriggerType::SHIPMENT_DELIVERED,
            default => MessageTriggerType::SHIPMENT_DISPATCHED,
        };

        return MessageTemplate::query()
            ->where('is_active', true)
            ->whereJsonContains('trigger_types', $trigger->value)
            ->orWhere('type', 'delivery_update')
            ->get();
    }

    /**
     * Get recommended templates for general exceptions.
     */
    public function recommendForException(Shipment $shipment, string $exceptionCode): Collection
    {
        return MessageTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereJsonContains('trigger_types', MessageTriggerType::EXCEPTION_OCCURRED->value)
                    ->orWhere('type', 'exception');
            })
            ->get();
    }

    /**
     * Get default placeholder values for an order/shipment context.
     */
    public function getPlaceholderContext(Order $order, ?Shipment $shipment = null): array
    {
        $context = [
            'order_number' => $order->number,
            'order_id' => (string)$order->id,
            'customer_name' => $order->customer?->name ?? $order->guest_name ?? 'Valued Customer',
            'tracking_number' => $shipment?->tracking_number ?? '',
            'carrier' => $shipment?->carrier ?? '',
            'estimated_delivery' => $shipment?->shipped_at?->addDays(7)->format('M d, Y') ?? '',
            'refund_amount' => '$' . $order->refund_amount ?? '',
            'exception_type' => $shipment?->exception_code?->label() ?? '',
        ];

        if ($order->customer) {
            $context['customer_email'] = $order->customer->email;
            $context['customer_phone'] = $order->customer->phone ?? '';
        }

        return $context;
    }

    /**
     * Schedule messages for common scenarios.
     */
    public function scheduleCommonScenarios(Order $order): void
    {
        // Order placed
        $this->service->scheduleAutoSend(
            MessageTriggerType::ORDER_PLACED,
            $order,
            $this->getPlaceholderContext($order),
        );

        // Payment received
        if ($order->payment_status === 'paid') {
            $this->service->scheduleAutoSend(
                MessageTriggerType::PAYMENT_RECEIVED,
                $order,
                $this->getPlaceholderContext($order),
            );
        }
    }

    /**
     * Build trigger action map.
     */
    public static function buildTriggerMap(): array
    {
        return [
            // Shipment triggers
            MessageTriggerType::SHIPMENT_DELAYED->value => [
                'name' => 'Shipment Delayed',
                'description' => 'Shipment has not arrived within expected timeframe',
                'conditions' => ['days_shipped' => 7],
                'recommended_templates' => ['delay'],
            ],
            MessageTriggerType::SHIPMENT_DISPATCHED->value => [
                'name' => 'Shipment Dispatched',
                'description' => 'Shipment has left warehouse',
                'conditions' => ['has_tracking'],
                'recommended_templates' => ['delivery_update', 'tracking'],
            ],
            MessageTriggerType::SHIPMENT_OUT_FOR_DELIVERY->value => [
                'name' => 'Out for Delivery',
                'description' => 'Shipment is out for delivery today',
                'conditions' => ['carrier_status'],
                'recommended_templates' => ['delivery_update'],
            ],
            MessageTriggerType::SHIPMENT_DELIVERED->value => [
                'name' => 'Delivered',
                'description' => 'Shipment has been delivered',
                'conditions' => ['delivered_at'],
                'recommended_templates' => ['delivery_update'],
            ],

            // Customs triggers
            MessageTriggerType::CUSTOMS_HOLD->value => [
                'name' => 'Customs Hold',
                'description' => 'Shipment is held in customs',
                'conditions' => ['exception_code' => 'CUSTOMS_*'],
                'recommended_templates' => ['customs'],
            ],
            MessageTriggerType::CUSTOMS_CLEARED->value => [
                'name' => 'Customs Cleared',
                'description' => 'Shipment cleared customs',
                'conditions' => ['customs_cleared_at'],
                'recommended_templates' => ['customs', 'delivery_update'],
            ],

            // Exception triggers
            MessageTriggerType::EXCEPTION_OCCURRED->value => [
                'name' => 'Exception Occurred',
                'description' => 'General shipment exception',
                'conditions' => ['exception_code'],
                'recommended_templates' => ['exception'],
            ],
            MessageTriggerType::EXCEPTION_RESOLVED->value => [
                'name' => 'Exception Resolved',
                'description' => 'Shipment exception has been resolved',
                'conditions' => ['resolved_at'],
                'recommended_templates' => ['exception'],
            ],

            // Split shipment triggers
            MessageTriggerType::SPLIT_SHIPMENT_CREATED->value => [
                'name' => 'Split Shipment',
                'description' => 'Order split into multiple shipments',
                'conditions' => ['shipment_count' => 2],
                'recommended_templates' => ['split_shipments'],
            ],
            MessageTriggerType::MULTIPLE_SHIPMENTS_MERGED->value => [
                'name' => 'Shipments Merged',
                'description' => 'Multiple shipments combined',
                'conditions' => [],
                'recommended_templates' => ['split_shipments'],
            ],

            // Refund triggers
            MessageTriggerType::REFUND_INITIATED->value => [
                'name' => 'Refund Initiated',
                'description' => 'Refund process started',
                'conditions' => ['refund_initiated_at'],
                'recommended_templates' => ['refund_update'],
            ],
            MessageTriggerType::REFUND_PROCESSED->value => [
                'name' => 'Refund Processed',
                'description' => 'Refund has been processed',
                'conditions' => ['refund_amount', 'refund_processed_at'],
                'recommended_templates' => ['refund_update'],
            ],
            MessageTriggerType::REFUND_FAILED->value => [
                'name' => 'Refund Failed',
                'description' => 'Refund processing failed',
                'conditions' => [],
                'recommended_templates' => ['refund_update'],
            ],

            // Order triggers
            MessageTriggerType::ORDER_PLACED->value => [
                'name' => 'Order Placed',
                'description' => 'New order received',
                'conditions' => ['order_id'],
                'recommended_templates' => ['general'],
            ],
            MessageTriggerType::PAYMENT_RECEIVED->value => [
                'name' => 'Payment Received',
                'description' => 'Payment confirmed',
                'conditions' => ['payment_status' => 'paid'],
                'recommended_templates' => ['general', 'delivery_update'],
            ],
        ];
    }

    /**
     * Get trigger documentation.
     */
    public static function getTriggerDocumentation(): array
    {
        return [
            'shipment_delayed' => 'Sent when shipment has no updates for X days',
            'shipment_dispatched' => 'Sent when shipment leaves warehouse',
            'shipment_out_for_delivery' => 'Sent when carrier indicates out for delivery',
            'shipment_delivered' => 'Sent when shipment is delivered',
            'customs_hold' => 'Sent when shipment enters customs hold',
            'customs_cleared' => 'Sent when shipment clears customs',
            'exception_occurred' => 'Sent when shipment exception is detected',
            'exception_resolved' => 'Sent when exception is resolved',
            'split_shipment_created' => 'Sent when order is split into multiple shipments',
            'multiple_shipments_merged' => 'Sent when multiple shipments are merged',
            'refund_initiated' => 'Sent when refund is initiated',
            'refund_processed' => 'Sent when refund is processed',
            'refund_failed' => 'Sent when refund fails',
            'order_placed' => 'Sent when order is created',
            'payment_received' => 'Sent when payment is confirmed',
        ];
    }
}
