<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'fulfillment_provider_id',
        'fulfillment_job_id',
        'type',
        'status',
        'message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class, 'fulfillment_provider_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(FulfillmentJob::class, 'fulfillment_job_id');
    }
}
