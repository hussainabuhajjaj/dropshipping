<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'shipment_id',
        'amount',
        'reason_code',
        'admin_notes',
        'customer_reason',
        'status',
        'approved_by',
        'approved_at',
        'transaction_id',
        'gateway_response',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    // Reason codes
    public const REASON_CUSTOMER_REQUEST = 'customer_request';
    public const REASON_DAMAGED = 'damaged';
    public const REASON_LATE_DELIVERY = 'late_delivery';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_QUALITY_ISSUE = 'quality_issue';
    public const REASON_DUPLICATE_ORDER = 'duplicate_order';
    public const REASON_OUT_OF_STOCK = 'out_of_stock';

    public static function reasonCodes(): array
    {
        return [
            self::REASON_CUSTOMER_REQUEST => 'Customer Request',
            self::REASON_DAMAGED => 'Item Damaged',
            self::REASON_LATE_DELIVERY => 'Late Delivery',
            self::REASON_WRONG_ITEM => 'Wrong Item Sent',
            self::REASON_QUALITY_ISSUE => 'Quality Issue',
            self::REASON_DUPLICATE_ORDER => 'Duplicate Order',
            self::REASON_OUT_OF_STOCK => 'Out of Stock',
        ];
    }

    // ==================== Relationships ====================

    /**
     * @return BelongsTo<Order, Refund>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, Refund>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Shipment, Refund>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // ==================== Accessors & Mutators ====================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
