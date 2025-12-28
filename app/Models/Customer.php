<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Domain\Common\Models\Address;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use SoftDeletes;
    use Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'country_code',
        'city',
        'region',
        'address_line1',
        'address_line2',
        'postal_code',
        'metadata',
        'password',
        'email_verified_at',
        'remember_token',
    ];

    protected $casts = [
        'metadata' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'name',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function giftCards(): HasMany
    {
        return $this->hasMany(GiftCard::class);
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function getNameAttribute(): string
    {
        $name = trim($this->first_name . ' ' . ($this->last_name ?? ''));
        return $name !== '' ? $name : ($this->email ?? 'Customer');
    }

    public function setNameAttribute(?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            $this->attributes['first_name'] = null;
            $this->attributes['last_name'] = null;
            return;
        }

        $parts = preg_split('/\s+/', $value);
        $first = array_shift($parts);
        $last = $parts ? implode(' ', $parts) : null;

        $this->attributes['first_name'] = $first;
        $this->attributes['last_name'] = $last;
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer): void {
            if (! array_key_exists('address_line1', $customer->getAttributes()) || $customer->address_line1 === null) {
                $customer->address_line1 = '';
            }
        });

        static::created(function (self $customer): void {
            // Auto-link past guest orders that match the registration email
            try {
                app(\App\Domain\Orders\Services\LinkGuestOrdersService::class)
                    ->linkByEmail((string) $customer->email, (int) $customer->id, null);
            } catch (\Throwable $e) {
                // Swallow exceptions to avoid blocking registration flow
            }
        });
    }
}
