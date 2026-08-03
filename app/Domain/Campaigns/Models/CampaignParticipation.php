<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Models;

use App\Domain\Campaigns\Enums\LuckyDrawParticipantState;
use App\Domain\Orders\Models\Order;
use App\Models\Customer;
use App\Models\StorefrontCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignParticipation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'customer_id',
        'spot_number',
        'state',
        'guaranteed_reward_type',
        'guaranteed_reward_value',
        'reward_code',
        'reward_issued_at',
        'qualified_at',
        'meta',
    ];

    protected $casts = [
        'spot_number' => 'integer',
        'guaranteed_reward_value' => 'decimal:2',
        'reward_issued_at' => 'datetime',
        'qualified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(StorefrontCampaign::class, 'campaign_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'campaign_participation_orders', 'participation_id', 'order_id')
            ->withPivot(['order_total', 'qualified_at'])
            ->withTimestamps();
    }

    public function winner(): HasOne
    {
        return $this->hasOne(CampaignWinner::class, 'participation_id');
    }

    public function hasReservedSpot(): bool
    {
        return $this->spot_number !== null && (int) $this->spot_number > 0;
    }

    public function inState(string|LuckyDrawParticipantState $state): bool
    {
        $value = $state instanceof LuckyDrawParticipantState ? $state->value : $state;

        return $this->state === $value;
    }

    public function markSpotReserved(int $spotNumber): void
    {
        $this->forceFill([
            'spot_number' => $spotNumber,
            'state' => LuckyDrawParticipantState::SPOT_RESERVED->value,
        ])->save();
    }

    public function markQualified(): void
    {
        if ($this->state === null) {
            $this->forceFill(['state' => LuckyDrawParticipantState::QUALIFIED->value])->save();
        }
    }

    public function issueReward(string $rewardCode, ?LuckyDrawParticipantState $state = null): void
    {
        $this->forceFill([
            'reward_code' => $rewardCode,
            'reward_issued_at' => now(),
            'state' => ($state ?? LuckyDrawParticipantState::REWARD_ISSUED)->value,
        ])->save();
    }
}
