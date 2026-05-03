<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;
use App\Domain\Common\Models\Address;
use App\Domain\Payments\Models\Payment;
use App\Enums\RefundReasonEnum;
use App\Models\OrderShipping;
use App\Models\PromotionUsage;
use App\Notifications\OrderStatusChanged;
use App\Notifications\RefundApproved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Services\Currency\CurrencyConversionService;

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
        'supplier_product_cost_total',
        'supplier_external_shipping_total',
        'supplier_cj_shipping_total',
        'supplier_total_cost',
        'gross_profit_amount',
        'gross_margin_percent',
        'cost_breakdown',
        'cost_calculated_at',
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
        'supplier_product_cost_total' => 'decimal:2',
        'supplier_external_shipping_total' => 'decimal:2',
        'supplier_cj_shipping_total' => 'decimal:2',
        'supplier_total_cost' => 'decimal:2',
        'gross_profit_amount' => 'decimal:2',
        'gross_margin_percent' => 'decimal:2',
        'cost_breakdown' => 'array',
        'cost_calculated_at' => 'datetime',
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
        $relation = $this->belongsTo(Address::class, 'shipping_address_id');

        return Address::softDeletesAvailable()
            ? $relation->withTrashed()
            : $relation;
    }

    public function billingAddress(): BelongsTo
    {
        $relation = $this->belongsTo(Address::class, 'billing_address_id');

        return Address::softDeletesAvailable()
            ? $relation->withTrashed()
            : $relation;
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
            'received', 'pending', 'paid' => 'Order received',
            'processing', 'fulfilling', 'fulfilled' => 'Processing',
            'dispatched' => 'Dispatched',
            'in_transit' => 'In transit',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'issue_detected' => 'Issue detected',
            'refunded', 'cancelled' => 'Refunded',
            default => 'Processing',
        };
    }

    /**
     * Get detailed explanation for customer-facing status.
     */
    public function getCustomerStatusExplanation(): string
    {
        return match ($this->customer_status ?? $this->status) {
            'received', 'pending', 'paid' => 'Payment confirmed. Your order is being prepared.',
            'processing', 'fulfilling', 'fulfilled' => 'We are preparing your shipment from the supplier.',
            'dispatched' => 'Your order has been shipped from the warehouse.',
            'in_transit' => 'Your package is on the way to your country.',
            'out_for_delivery' => 'Your package is out for delivery today.',
            'delivered' => 'Your order has been delivered. Thank you!',
            'issue_detected' => 'There is an issue with your order. Our team will contact you shortly.',
            'refunded', 'cancelled' => 'This order has been refunded.',
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
        return 'DS-' . Str::upper(Str::random(12));
    }

    public static function sumAmountInAdminCurrency(string $field, ?Builder $query = null): float
    {
        $query = $query ?? static::query();
        $rows = (clone $query)
            ->selectRaw('COALESCE(currency, ?) as currency, COALESCE(SUM(' . $field . '), 0) as total', [config('currency.base', 'USD')])
            ->groupBy('currency')
            ->get();

        $converter = app(CurrencyConversionService::class);

        return (float) $rows->sum(fn ($row) => (float) $converter->convertAmount(
            (float) ($row->total ?? 0.0),
            (string) ($row->currency ?? config('currency.base', 'USD')),
            'USD',
        ));
    }

    public static function dailySumsInAdminCurrency(string $field, Carbon $start, Carbon $end, ?Builder $query = null): Collection
    {
        $query = $query ?? static::query();
        $rows = (clone $query)
            ->selectRaw('DATE(created_at) as day, currency, COALESCE(SUM(' . $field . '), 0) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('day', 'currency')
            ->orderBy('day')
            ->get();

        $converter = app(CurrencyConversionService::class);

        return $rows->groupBy('day')->mapWithKeys(function ($group, $day) use ($converter) {
            return [
                $day => (float) $group->sum(fn ($row) => (float) $converter->convertAmount(
                    (float) ($row->total ?? 0.0),
                    (string) ($row->currency ?? config('currency.base', 'USD')),
                    'USD',
                )),
            ];
        });
    }

    public static function createWithGeneratedNumber(array $attributes, int $maxAttempts = 5): static
    {
        unset($attributes['number']);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return static::query()->create([
                    ...$attributes,
                    'number' => static::generateOrderNumber(),
                ]);
            } catch (QueryException $exception) {
                if (! static::isDuplicateOrderNumberException($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to generate a unique order number.');
    }

    private static function isDuplicateOrderNumberException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        if (in_array($sqlState, ['23000', '23505'], true) || $driverCode === '1062') {
            return str_contains($message, 'number') || str_contains($message, 'orders_number_unique');
        }

        return str_contains($message, 'duplicate') && str_contains($message, 'number');
    }

    public function recordPromotionUsage(array $promotionDiscounts, float $subtotal, ?string $campaignSource): void
    {
        if (empty($promotionDiscounts)) {
            return;
        }

        $now = now();
        foreach ($promotionDiscounts as $discount) {
            if (empty($discount['promotion_id'])) {
                continue;
            }
            PromotionUsage::create([
                'promotion_id' => $discount['promotion_id'],
                'user_id' => null,
                'order_id' => $this->id,
                'discount_amount' => $discount['amount'] ?? null,
                'used_at' => $now,
                'meta' => [
                    'promotion_intent' => $discount['intent'] ?? null,
                    'pre_discount_subtotal' => $subtotal,
                    'discount_breakdown' => $promotionDiscounts,
                    'chosen_campaign_source' => $campaignSource,
                ],
            ]);
        }

        $intents = collect($promotionDiscounts)->pluck('intent')->filter()->unique()->values();
        $intentLabels = $intents->map(function ($intent) {
            return match ($intent) {
                'shipping_support' => 'Logistics support applied',
                'cart_growth' => 'Cart growth discount applied',
                'urgency' => 'Flash deal applied',
                'acquisition' => 'Acquisition offer applied',
                default => 'Promotion applied',
            };
        })->unique()->values()->all();

        OrderAuditLog::create([
            'order_id' => $this->id,
            'user_id' => null,
            'action' => 'promotion_applied',
            'note' => $intentLabels ? implode(' | ', $intentLabels) : 'Promotions applied during checkout',
            'payload' => [
                'discounts' => $promotionDiscounts,
                'pre_discount_subtotal' => $subtotal,
                'chosen_campaign_source' => $campaignSource,
            ],
        ]);
    }


}
