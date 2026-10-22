<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignParticipationOrder extends Model
{
    protected $table = 'campaign_participation_orders';

    protected $fillable = [
        'participation_id',
        'order_id',
        'order_total',
        'qualified_at',
        'meta',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'qualified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipation::class, 'participation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
