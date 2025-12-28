<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipping_zone_id',
        'name',
        'min_weight',
        'max_weight',
        'min_price',
        'max_price',
        'rate',
        'carrier',
        'delivery_min_days',
        'delivery_max_days',
        'is_active',
    ];

    protected $casts = [
        'min_weight' => 'float',
        'max_weight' => 'float',
        'min_price' => 'float',
        'max_price' => 'float',
        'rate' => 'float',
        'delivery_min_days' => 'integer',
        'delivery_max_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
