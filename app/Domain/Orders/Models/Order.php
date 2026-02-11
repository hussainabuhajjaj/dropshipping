<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Common\Models\Address;
use App\Domain\Payments\Models\Payment;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Enums\RefundReasonEnum;
use App\Models\OrderShipping;
use App\Notifications\OrderStatusChanged;
use App\Notifications\RefundApproved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'locale',
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
        'discount_snapshot',
        'discount_source',
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
        // CJ Payment tracking
        'cj_order_id',
        'cj_shipment_order_id',
        'cj_order_status',
        'cj_order_created_at',
        'cj_confirmed_at',
        'cj_payment_status',
        'cj_pay_id',
        'cj_amount_due',
        'cj_paid_at',
        'cj_payment_error',
        'cj_payment_idempotency_key',
        'cj_payment_attempts',
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
        'discount_snapshot' => 'array',
        // CJ Payment tracking
        'cj_order_created_at' => 'datetime',
        'cj_confirmed_at' => 'datetime',
        'cj_paid_at' => 'datetime',
        'cj_amount_due' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function orderShippings()
    {
        return $this->hasMany(OrderShipping::class);
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

    public function linehaulShipment(): HasOne
    {
        return $this->hasOne(LinehaulShipment::class);
    }

    public function lastMileDelivery(): HasOne
    {
        return $this->hasOne(LastMileDelivery::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function notificationLocale(): string
    {
        return $this->locale
            ?? $this->customer?->locale
            ?? config('app.locale', 'en');
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
        return !in_array($this->status, ['delivered', 'refunded'], true);
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
            if (in_array($newStatus, ['in_transit', 'out_for_delivery'], true)) {
                $appOrder = $this instanceof \App\Models\Order
                    ? $this
                    : \App\Models\Order::query()->find($this->id);

                if ($appOrder) {
                    $this->customer->notify(new \App\Notifications\Orders\InTransitNotification($appOrder));
                }

                return;
            }

            $this->customer->notify(new OrderStatusChanged($this, $previousStatus, $newStatus));
        }
    }

    public static function generateOrderNumber(): string
    {
        $max = self::query()->selectRaw("
                MAX(
                    CAST(
                        SUBSTRING_INDEX(number, '-', -1) AS UNSIGNED
                    )
                ) as max_number
            ")
            ->value('max_number');


        $next = ($max ?? 0) + 1;
        return 'DS-' . str_pad((string)$next, 10, '0', STR_PAD_LEFT);
//
//        do {
//            $number = 'DS-' . Str::upper(Str::random(8));
//        } while (self::where('number', $number)->exists());
//
//        return $number;
    }

}
