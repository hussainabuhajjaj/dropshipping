<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Models\ProductMarginLog;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'cj_pid',
        'cj_sync_enabled',
        'cj_warehouse_id',
        'cj_warehouse_name',
        'cj_synced_at',
        'cj_last_payload',
        'cj_last_changed_fields',
        'cj_lock_price',
        'cj_lock_description',
        'cj_lock_images',
        'cj_lock_variants',
        'cj_video_urls',
        'stock_on_hand',
        'slug',
        'name',
        'category_id',
        'description',
        'meta_title',
        'meta_description',
        'selling_price',
        'cost_price',
        'status',
        'currency',
        'default_fulfillment_provider_id',
        'supplier_id',
        'supplier_product_url',
        'shipping_estimate_days',
        'is_active',
        'is_featured',
        'source_url',
        'options',
        'attributes',
        'seo_metadata',
        'marketing_metadata',
        'translation_status',
        'translated_locales',
        'last_translation_at',
    ];

    protected $casts = [
        'options' => 'array',
        'attributes' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'cj_sync_enabled' => 'boolean',
        'cj_synced_at' => 'datetime',
        'cj_last_payload' => 'array',
        'cj_last_changed_fields' => 'array',
        'cj_lock_price' => 'boolean',
        'cj_lock_description' => 'boolean',
        'cj_lock_images' => 'boolean',
        'cj_lock_variants' => 'boolean',
        'cj_video_urls' => 'array',
        'stock_on_hand' => 'integer',
        'seo_metadata' => 'array',
        'marketing_metadata' => 'array',
        'translated_locales' => 'array',
        'last_translation_at' => 'datetime',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function defaultFulfillmentProvider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class, 'default_fulfillment_provider_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class, 'supplier_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(\App\Models\ProductReview::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function marginLogs(): HasMany
    {
        return $this->hasMany(ProductMarginLog::class, 'product_id');
    }

    public function translationForLocale(?string $locale): ?ProductTranslation
    {
        if (! $locale) {
            return null;
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            // Ensure a slug exists for DB constraints and tests
            if (! $product->slug || trim((string) $product->slug) === '') {
                $name = (string) $product->name;
                $candidate = Str::slug($name);
                if ($candidate === '') {
                    $candidate = 'product-' . Str::random(8);
                }

                $product->slug = $candidate;
            }
        });
    }
}
