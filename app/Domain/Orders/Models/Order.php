<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Common\Models\Address;
use App\Domain\Payments\Models\Payment;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Enums\RefundReasonEnum;
use App\Notifications\OrderStatusChanged;
use App\Notifications\RefundApproved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'user_id',
        'customer_id',
        'guest_name',
        'guest_phone',
        'is_guest',
        'email',
        'status',
        'customer_status',
        'payment_status',
        'currency',
        'subtotal',
        'shipping_total',
        'shipping_total_estimated',
        'shipping_total_actual',
        'shipping_reconciled_at',
        'shipping_variance',
        'tax_total',
        'discount_total',
        'grand_total',
        'refund_reason',
        'refund_amount',
        'refund_notes',
        'shipping_address_id',
        'billing_address_id',
        'shipping_method',
        'delivery_notes',
        'coupon_code',
        'placed_at',
        'refunded_at',
        'policies_version',
        'policies_hash',
        'policies_accepted_at',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'shipping_reconciled_at' => 'datetime',
        'policies_accepted_at' => 'datetime',
        'is_guest' => 'boolean',
        'shipping_total' => 'decimal:2',
        'shipping_total_estimated' => 'decimal:2',
        'shipping_total_actual' => 'decimal:2',
        'shipping_variance' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_reason' => RefundReasonEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(OrderAuditLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function chargebackCases(): HasMany
    {
        return $this->hasMany(ChargebackCase::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(\App\Domain\Messaging\Models\MessageLog::class);
    }

    public function paymentEvents(): HasManyThrough
    {
        return $this->hasManyThrough(\App\Domain\Payments\Models\PaymentEvent::class, Payment::class);
    }

    public function fulfillmentEvents(): HasManyThrough
    {
        return $this->hasManyThrough(\App\Domain\Fulfillment\Models\FulfillmentEvent::class, OrderItem::class);
    }

    public function shipments(): HasManyThrough
    {
        return $this->hasManyThrough(Shipment::class, OrderItem::class, 'order_id', 'order_item_id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    /**
     * Get human-readable customer-facing status with explanation.
     */
    public function getCustomerStatusLabel(): string
    {
        return match ($this->customer_status ?? $this->status) {
            'received' => 'Order received',
            'processing' => 'Processing',
            'dispatched' => 'Dispatched',
            'in_transit' => 'In transit',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'issue_detected' => 'Issue detected',
            'refunded' => 'Refunded',
            default => ucfirst(str_replace('_', ' ', $this->customer_status ?? $this->status)),
        };
    }

    /**
     * Get detailed explanation for customer-facing status.
     */
    public function getCustomerStatusExplanation(): string
    {
        return match ($this->customer_status ?? $this->status) {
            'received' => 'Payment confirmed. Your order is being prepared.',
            'processing' => 'We are preparing your shipment from the supplier.',
            'dispatched' => 'Your order has been shipped from the warehouse.',
            'in_transit' => 'Your package is on the way to your country.',
            'out_for_delivery' => 'Your package is out for delivery today.',
            'delivered' => 'Your order has been delivered. Thank you!',
            'issue_detected' => 'There is an issue with your order. Our team will contact you shortly.',
            'refunded' => 'This order has been refunded.',
            default => 'Your order is being processed.',
        };
    }

    /**
     * Check if order can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return ! in_array($this->status, ['delivered', 'refunded'], true);
    }

    /**
     * Mark order as refunded with reason.
     */
    public function markRefunded(RefundReasonEnum $reason, int $amount = 0, ?string $notes = null): void
    {
        $previousStatus = $this->customer_status;

        $this->update([
            'status' => 'refunded',
            'customer_status' => 'refunded',
            'refund_reason' => $reason,
            'refund_amount' => $amount ?? $this->grand_total,
            'refund_notes' => $notes,
            'refunded_at' => now(),
        ]);

        // Notify customer of refund
        if ($this->customer) {
            $this->customer->notify(new RefundApproved($this));
        }

        // Fire status changed event
        if ($previousStatus !== 'refunded') {
            if ($this->customer) {
                $this->customer->notify(new OrderStatusChanged($this, $previousStatus, 'refunded'));
            }
        }
    }

    /**
     * Update customer status and notify.
     */
    public function updateCustomerStatus(string $newStatus): void
    {
        $previousStatus = $this->customer_status;

        if ($previousStatus === $newStatus) {
            return; // No change, skip notification
        }

        $this->update(['customer_status' => $newStatus]);

        // Notify customer of status change
        if ($this->customer) {
            $this->customer->notify(new OrderStatusChanged($this, $previousStatus, $newStatus));
        }
    }
}
