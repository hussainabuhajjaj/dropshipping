<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AliExpressSubOrder extends Model
{
    protected $fillable = [
        'order_item_id',
        'ali_order_id',
        'payload_snapshot',
    ];

    protected $casts = [
        'payload_snapshot' => 'array',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
