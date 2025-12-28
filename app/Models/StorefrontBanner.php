<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class StorefrontBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'display_type',
        'target_type',
        'product_id',
        'category_id',
        'external_url',
        'image_path',
        'background_color',
        'text_color',
        'badge_text',
        'badge_color',
        'cta_text',
        'cta_url',
        'starts_at',
        'ends_at',
        'is_active',
        'display_order',
        'targeting',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'targeting' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope for active banners within date range
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Scope for banners by display type
     */
    public function scopeByDisplayType(Builder $query, string $displayType): Builder
    {
        return $query->where('display_type', $displayType);
    }

    /**
     * Scope for banners by type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get banners for a specific category
     */
    public function scopeForCategory(Builder $query, ?int $categoryId): Builder
    {
        return $query->where(function (Builder $q) use ($categoryId) {
            $q->whereNull('target_type')
                ->orWhere('target_type', 'category')
                ->where('category_id', $categoryId);
        });
    }

    /**
     * Get banners for a specific product
     */
    public function scopeForProduct(Builder $query, ?int $productId): Builder
    {
        return $query->where(function (Builder $q) use ($productId) {
            $q->whereNull('target_type')
                ->orWhere('target_type', 'product')
                ->where('product_id', $productId);
        });
    }

    /**
     * Get URL for the banner CTA
     */
    public function getCtaUrl(): ?string
    {
        if ($this->cta_url) {
            return $this->cta_url;
        }

        if ($this->target_type === 'product' && $this->product) {
            return $this->product->url ?? route('product.show', $this->product);
        }

        if ($this->target_type === 'category' && $this->category) {
            return route('category.show', $this->category);
        }

        return $this->external_url;
    }

    /**
     * Check if banner is currently active
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && now() < $this->starts_at) {
            return false;
        }

        if ($this->ends_at && now() > $this->ends_at) {
            return false;
        }

        return true;
    }
}
