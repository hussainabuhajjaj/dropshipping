<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinehaulShipment extends Model
{
    protected $fillable = [
        'order_id',
        'tracking_number',
        'total_weight_kg',
        'base_fee',
        'per_kg_rate',
        'total_fee',
        'shipment_snapshot',
        'dispatched_at',
        'arrived_at',
    ];

    protected $casts = [
        'shipment_snapshot' => 'array',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
