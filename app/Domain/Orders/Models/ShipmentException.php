<?php

namespace App\Domain\Orders\Models;

use App\Enums\ShipmentExceptionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentException extends Model
{
    protected $fillable = [
        'shipment_id',
        'exception_code',
        'exception_reason',
        'occurred_at',
        'source',
        'raw_data',
        'created_by',
    ];

    protected $casts = [
        'exception_code' => ShipmentExceptionCode::class,
        'occurred_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * @return BelongsTo<Shipment, ShipmentException>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function resolutions()
    {
        return $this->hasMany(ShipmentExceptionResolution::class, 'exception_id');
    }

    public function latestResolution()
    {
        return $this->hasOne(ShipmentExceptionResolution::class, 'exception_id')
            ->latestOfMany('resolved_at');
    }
}
