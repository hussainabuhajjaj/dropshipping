<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppOrderIntent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'whatsapp_order_intents';

    protected $fillable = [
        'reference',
        'status',
        'channel',
        'intent_type',
        'customer_id',
        'session_id',
        'guest_token',
        'phone',
        'currency',
        'items_count',
        'subtotal',
        'shipping_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'snapshot',
        'pricing_hash',
        'snapshot_version',
        'source_url',
        'user_agent',
        'ip_address',
        'expires_at',
        'opened_at',
        'converted_at',
        'converted_order_id',
        'converted_by_admin_id',
        'last_error_code',
        'last_error_message',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function convertedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by_admin_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function markExpired(?string $code = 'expired'): void
    {
        $this->forceFill([
            'status' => self::STATUS_EXPIRED,
            'last_error_code' => $code,
            'last_error_message' => 'Intent expired before conversion.',
        ])->save();
    }
}
