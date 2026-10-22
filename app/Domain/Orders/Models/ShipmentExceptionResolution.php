<?php

namespace App\Domain\Orders\Models;

use App\Enums\ShipmentExceptionResolutionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentExceptionResolution extends Model
{
    protected $fillable = [
        'shipment_id',
        'exception_id',
        'resolution_code',
        'admin_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolution_code' => ShipmentExceptionResolutionCode::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Shipment, ShipmentExceptionResolution>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<ShipmentException, ShipmentExceptionResolution>
     */
    public function exception(): BelongsTo
    {
        return $this->belongsTo(ShipmentException::class, 'exception_id');
    }

    /**
     * @return BelongsTo
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'resolved_by');
    }
}
