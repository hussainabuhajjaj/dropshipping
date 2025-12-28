<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'type',
        'amount',
        'min_order_total',
        'max_uses',
        'uses',
        'is_active',
        'starts_at',
        'ends_at',
        'meta',
        'applicable_to',
        'exclude_on_sale',
        'is_one_time_per_customer',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'min_order_total' => 'decimal:2',
        'is_active' => 'boolean',
        'exclude_on_sale' => 'boolean',
        'is_one_time_per_customer' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function isValidForProduct(Product $product): bool
    {
        if ($this->applicable_to === 'all') {
            return true;
        }

        if ($this->applicable_to === 'products') {
            return $this->products->contains($product);
        }

        if ($this->applicable_to === 'categories') {
            return $this->categories->contains($product->category);
        }

        if ($this->exclude_on_sale && $product->is_on_sale) {
            return false;
        }

        return false;
    }

    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->isBefore($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->isAfter($this->ends_at)) {
            return false;
        }

        if ($this->max_uses && $this->uses >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
