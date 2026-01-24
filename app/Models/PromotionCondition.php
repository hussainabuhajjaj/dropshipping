<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    public const CONDITION_SKIP_MIN_CART_TOTAL = 'skip_min_cart_total';

    protected $fillable = [
        'promotion_id', 'condition_type', 'condition_value',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
