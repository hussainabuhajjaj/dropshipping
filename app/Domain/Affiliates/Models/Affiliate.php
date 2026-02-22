<?php

namespace App\Domain\Affiliates\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class Affiliate extends Authenticatable implements FilamentUser
{
    use HasFactory;

    protected $fillable = [
        'referral_code',
        'status',
        'commission_rate',
        'balance_pending',
        'balance_available',
        'total_earned',
        'name',
        'email',
        'password',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:4',
        'balance_pending' => 'decimal:2',
        'balance_available' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'email_verified_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Affiliate $affiliate) {
            if (! $affiliate->referral_code) {
                do {
                    $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
                } while (Affiliate::query()->where('referral_code', $code)->exists());

                $affiliate->referral_code = $code;
        }
        });
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(AffiliateWithdrawal::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status !== 'suspended';
    }

    public function setPasswordAttribute(?string $value): void
    {
        if (! filled($value)) {
            return;
        }

        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    protected static function newFactory(): \Database\Factories\AffiliateFactory
    {
        return \Database\Factories\AffiliateFactory::new();
    }
}
