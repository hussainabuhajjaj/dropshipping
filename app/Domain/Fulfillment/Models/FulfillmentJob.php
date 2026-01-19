<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'fulfillment_provider_id',
        'payload',
        'status',
        'external_reference',
        'last_error',
        'dispatched_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'dispatched_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class, 'fulfillment_provider_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(FulfillmentAttempt::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class, 'fulfillment_job_id');
    }
}
