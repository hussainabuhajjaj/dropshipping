<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\LuckyDrawPrizeType;
use App\Models\Customer;
use App\Models\StorefrontCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignWinner extends Model
{
    protected $fillable = [
        'campaign_id',
        'participation_id',
        'customer_id',
        'prize_type',
        'prize_value',
        'prize_label',
        'reward_code',
        'status',
        'announced_at',
        'delivered_at',
        'meta',
    ];

    protected $casts = [
        'prize_value' => 'decimal:2',
        'announced_at' => 'datetime',
        'delivered_at' => 'datetime',
        'meta' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(StorefrontCampaign::class, 'campaign_id');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(CampaignParticipation::class, 'participation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function prize(): LuckyDrawPrizeType
    {
        return LuckyDrawPrizeType::tryFrom((string) $this->prize_type) ?? LuckyDrawPrizeType::GUARANTEED;
    }

    public function announce(): void
    {
        $this->forceFill(['announced_at' => now(), 'status' => 'pending'])->save();
    }

    public function markDelivered(?string $status = 'delivered'): void
    {
        $this->forceFill(['delivered_at' => now(), 'status' => $status])->save();
    }
}
