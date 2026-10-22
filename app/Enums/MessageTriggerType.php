<?php

namespace App\Enums;

enum MessageTriggerType: string
{
    // Shipment triggers
    case SHIPMENT_DELAYED = 'shipment_delayed';
    case SHIPMENT_DISPATCHED = 'shipment_dispatched';
    case SHIPMENT_OUT_FOR_DELIVERY = 'shipment_out_for_delivery';
    case SHIPMENT_DELIVERED = 'shipment_delivered';
    
    // Customs triggers
    case CUSTOMS_HOLD = 'customs_hold';
    case CUSTOMS_CLEARED = 'customs_cleared';
    
    // Exception triggers
    case EXCEPTION_OCCURRED = 'exception_occurred';
    case EXCEPTION_RESOLVED = 'exception_resolved';
    
    // Split shipment triggers
    case SPLIT_SHIPMENT_CREATED = 'split_shipment_created';
    case MULTIPLE_SHIPMENTS_MERGED = 'multiple_shipments_merged';
    
    // Refund triggers
    case REFUND_INITIATED = 'refund_initiated';
    case REFUND_PROCESSED = 'refund_processed';
    case REFUND_FAILED = 'refund_failed';
    
    // General triggers
    case ORDER_PLACED = 'order_placed';
    case PAYMENT_RECEIVED = 'payment_received';

    public function label(): string
    {
        return match ($this) {
            self::SHIPMENT_DELAYED => 'Shipment Delayed',
            self::SHIPMENT_DISPATCHED => 'Shipment Dispatched',
            self::SHIPMENT_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::SHIPMENT_DELIVERED => 'Delivered',
            self::CUSTOMS_HOLD => 'Customs Hold',
            self::CUSTOMS_CLEARED => 'Customs Cleared',
            self::EXCEPTION_OCCURRED => 'Exception Occurred',
            self::EXCEPTION_RESOLVED => 'Exception Resolved',
            self::SPLIT_SHIPMENT_CREATED => 'Split Shipment Created',
            self::MULTIPLE_SHIPMENTS_MERGED => 'Multiple Shipments Merged',
            self::REFUND_INITIATED => 'Refund Initiated',
            self::REFUND_PROCESSED => 'Refund Processed',
            self::REFUND_FAILED => 'Refund Failed',
            self::ORDER_PLACED => 'Order Placed',
            self::PAYMENT_RECEIVED => 'Payment Received',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::SHIPMENT_DELAYED, 
            self::SHIPMENT_DISPATCHED, 
            self::SHIPMENT_OUT_FOR_DELIVERY, 
            self::SHIPMENT_DELIVERED => 'Shipment',
            
            self::CUSTOMS_HOLD, 
            self::CUSTOMS_CLEARED => 'Customs',
            
            self::EXCEPTION_OCCURRED, 
            self::EXCEPTION_RESOLVED => 'Exceptions',
            
            self::SPLIT_SHIPMENT_CREATED, 
            self::MULTIPLE_SHIPMENTS_MERGED => 'Split Shipments',
            
            self::REFUND_INITIATED, 
            self::REFUND_PROCESSED, 
            self::REFUND_FAILED => 'Refunds',
            
            self::ORDER_PLACED, 
            self::PAYMENT_RECEIVED => 'Order',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function grouped(): array
    {
        return collect(self::cases())
            ->groupBy(fn ($case) => $case->group())
            ->mapWithKeys(fn ($group, $key) => [
                $key => $group->mapWithKeys(fn ($case) => [$case->value => $case->label()])->toArray()
            ])
            ->toArray();
    }
}
