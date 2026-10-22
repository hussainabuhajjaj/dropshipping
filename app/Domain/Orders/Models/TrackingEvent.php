<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'external_id',
        'status_code',
        'status_label',
        'description',
        'location',
        'occurred_at',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
