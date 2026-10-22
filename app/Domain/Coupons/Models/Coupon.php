<?php

namespace App\Domain\Coupons\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends \App\Models\Coupon
{
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Affiliates\Models\Affiliate::class, 'affiliate_id');
    }
}
