<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUsage extends Model
{
    protected $fillable = [
        'promotion_id',
        'user_id',
        'order_id',
        'discount_amount',
        'used_at',
        'meta',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'discount_amount' => 'float',
        'meta' => 'array',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
