<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundReasonEnum: string
{
    // Supplier failures
    case ITEM_UNAVAILABLE = 'item_unavailable';
    case SHIPMENT_LOST = 'shipment_lost';
    case EXCESSIVE_DELAY = 'excessive_delay';
    case SUPPLIER_ERROR = 'supplier_error';

    // Customer issues
    case CUSTOMER_UNREACHABLE = 'customer_unreachable';
    case WRONG_ADDRESS = 'wrong_address';
    case ADDRESS_REFUSED = 'address_refused';

    // Quality/Satisfaction
    case PRODUCT_MISMATCH = 'product_mismatch';
    case QUALITY_COMPLAINT = 'quality_complaint';
    case CHANGED_MIND = 'changed_mind';

    // Admin/Other
    case DUPLICATE_ORDER = 'duplicate_order';
    case MANUAL_OVERRIDE = 'manual_override';

    public function label(): string
    {
        return match ($this) {
            self::ITEM_UNAVAILABLE => 'Item unavailable',
            self::SHIPMENT_LOST => 'Shipment lost',
            self::EXCESSIVE_DELAY => 'Excessive delay',
            self::SUPPLIER_ERROR => 'Supplier error',
            self::CUSTOMER_UNREACHABLE => 'Customer unreachable',
            self::WRONG_ADDRESS => 'Wrong address provided',
            self::ADDRESS_REFUSED => 'Address refused delivery',
            self::PRODUCT_MISMATCH => 'Product mismatch',
            self::QUALITY_COMPLAINT => 'Quality complaint',
            self::CHANGED_MIND => 'Customer changed mind',
            self::DUPLICATE_ORDER => 'Duplicate order',
            self::MANUAL_OVERRIDE => 'Manual admin override',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ITEM_UNAVAILABLE => 'Supplier indicated item is out of stock.',
            self::SHIPMENT_LOST => 'Package was lost during shipping.',
            self::EXCESSIVE_DELAY => 'Order took significantly longer than expected.',
            self::SUPPLIER_ERROR => 'Supplier error or fulfillment failure.',
            self::CUSTOMER_UNREACHABLE => 'Unable to contact customer for delivery.',
            self::WRONG_ADDRESS => 'Customer provided incorrect shipping address.',
            self::ADDRESS_REFUSED => 'Customer or recipient refused delivery.',
            self::PRODUCT_MISMATCH => 'Item received does not match order.',
            self::QUALITY_COMPLAINT => 'Customer reports quality issues with product.',
            self::CHANGED_MIND => 'Customer requested cancellation after order.',
            self::DUPLICATE_ORDER => 'Order was accidentally duplicated.',
            self::MANUAL_OVERRIDE => 'Manual refund authorized by admin.',
        };
    }

    /**
     * Get refund percentage (0-100) for this reason.
     * Used to calculate partial refunds.
     */
    public function refundPercentage(): int
    {
        return match ($this) {
            // Full refund scenarios
            self::ITEM_UNAVAILABLE,
            self::SHIPMENT_LOST,
            self::SUPPLIER_ERROR,
            self::DUPLICATE_ORDER => 100,

            // Mostly refund (deduct shipping)
            self::EXCESSIVE_DELAY,
            self::CUSTOMER_UNREACHABLE,
            self::WRONG_ADDRESS,
            self::ADDRESS_REFUSED => 85,

            // Partial refund (customer satisfaction balance)
            self::PRODUCT_MISMATCH,
            self::QUALITY_COMPLAINT => 75,

            // Minimal refund (customer-initiated)
            self::CHANGED_MIND => 70,

            // Admin discretion
            self::MANUAL_OVERRIDE => 100,
        };
    }
}
