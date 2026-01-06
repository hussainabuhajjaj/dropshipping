<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LastMileDelivery extends Model
{
    protected $fillable = [
        'order_id',
        'yango_reference',
        'driver_name',
        'driver_phone',
        'delivery_fee',
        'status',
        'out_for_delivery_at',
        'delivered_at',
    ];

    protected $casts = [
        'out_for_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
