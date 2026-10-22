<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QrCampaign extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'reward_type',
        'reward_value',
        'product_id',
        'is_active',
        'claim_count',
        'max_claims',
        'starts_at',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'claim_count' => 'integer',
        'max_claims' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
        'reward_value' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(QrCampaignClaim::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('max_claims')->orWhere('claim_count', '<', $q->raw('max_claims')));
    }

    public function isClaimable(): bool
    {
        if (! $this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_claims && $this->claim_count >= $this->max_claims) return false;
        return true;
    }

    public function hasCustomerClaimed(Customer $customer): bool
    {
        return $this->claims()->where('customer_id', $customer->id)->exists();
    }

    public function rewardLabel(): string
    {
        return match ($this->reward_type) {
            'product' => $this->product?->name ?? 'Free product',
            'money' => number_format((float) $this->reward_value, 0) . ' ' . ($this->meta['currency'] ?? 'FCFA'),
            'points' => number_format((float) $this->reward_value, 0) . ' points',
            default => 'Reward',
        };
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (! $campaign->slug || trim($campaign->slug) === '') {
                $campaign->slug = Str::random(8);
            }
        });
    }
}
