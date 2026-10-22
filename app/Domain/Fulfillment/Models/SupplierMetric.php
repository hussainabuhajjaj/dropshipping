<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'fulfillment_provider_id',
        'fulfilled_count',
        'failed_count',
        'refunded_count',
        'average_lead_time_days',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class, 'fulfillment_provider_id');
    }
}
