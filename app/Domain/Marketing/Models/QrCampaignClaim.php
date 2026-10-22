<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCampaignClaim extends Model
{
    protected $fillable = [
        'qr_campaign_id',
        'customer_id',
        'claimed_at',
        'reward_delivered',
        'reward_delivered_at',
        'meta',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'reward_delivered' => 'boolean',
        'reward_delivered_at' => 'datetime',
        'meta' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(QrCampaign::class, 'qr_campaign_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
