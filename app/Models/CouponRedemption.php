<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'status',
        'redeemed_at',
        'meta',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
