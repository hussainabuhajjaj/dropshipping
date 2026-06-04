<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GiftCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'code',
        'balance',
        'currency',
        'status',
        'expires_at',
        'redeemed_at',
        'meta',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domain\Orders\Models\Order::class, 'order_gift_card')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where('balance', '>', 0);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
