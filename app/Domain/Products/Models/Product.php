<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Models\ProductMarginLog;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

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
        'cj_removed_from_shelves_at',
        'cj_removed_reason',
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
        'cj_removed_from_shelves_at' => 'datetime',
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

    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItem::class,
            ProductVariant::class,
            'product_id',
            'product_variant_id',
            'id',
            'id'
        );
    }

    public function marginLogs(): HasMany
    {
        return $this->hasMany(ProductMarginLog::class, 'product_id');
    }

    public function latestMarginLog(): HasOne
    {
        return $this->hasOne(ProductMarginLog::class, 'product_id')
            ->whereNull('variant_id')
            ->latestOfMany();
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

    public function scopePriceRange(Builder $query, ?float $min, ?float $max): Builder
    {
        if ($min === null && $max === null) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($min, $max) {
            $builder->where(function (Builder $priceQuery) use ($min, $max) {
                if ($min !== null) {
                    $priceQuery->where('selling_price', '>=', $min);
                }
                if ($max !== null) {
                    $priceQuery->where('selling_price', '<=', $max);
                }
            });

            $builder->orWhereHas('variants', function (Builder $variantQuery) use ($min, $max) {
                if ($min !== null) {
                    $variantQuery->where('price', '>=', $min);
                }
                if ($max !== null) {
                    $variantQuery->where('price', '<=', $max);
                }
            });
        });
    }

    public function scopeWithQualityScore(Builder $query, int $staleHours = 24): Builder
    {
        $staleHours = max(1, $staleHours);
        $staleCutoff = now()->subHours($staleHours)->toDateTimeString();
        $marginFactor = (1 + ((float) config('pricing.min_margin_percent', 20) / 100))
            * (1 + ((float) config('pricing.shipping_buffer_percent', 10) / 100));

        $sql = <<<SQL
GREATEST(
    0,
    LEAST(
        100,
        (
            100
            - CASE
                WHEN products.selling_price IS NULL OR products.selling_price <= 0
                    THEN 35
                ELSE 0
            END
            - CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM product_variants pv
                    WHERE pv.product_id = products.id
                        AND (pv.price IS NULL OR pv.price <= 0)
                ) THEN 20
                ELSE 0
            END
            - CASE
                WHEN NOT EXISTS (
                    SELECT 1
                    FROM product_images pi
                    WHERE pi.product_id = products.id
                ) THEN 10
                ELSE 0
            END
            - CASE
                WHEN products.translation_status IS NULL
                    OR products.translation_status = 'not translated'
                    OR NOT EXISTS (
                        SELECT 1
                        FROM product_translations pt
                        WHERE pt.product_id = products.id
                    ) THEN 15
                ELSE 0
            END
            - CASE
                WHEN products.cost_price IS NULL
                    OR products.cost_price <= 0
                    OR products.selling_price < (products.cost_price * ?)
                    THEN 10
                ELSE 0
            END
            - CASE
                WHEN products.cj_pid IS NOT NULL
                    AND products.cj_sync_enabled = 1
                    AND (products.cj_synced_at IS NULL OR products.cj_synced_at < ?)
                    THEN 10
                ELSE 0
            END
        )
    )
) AS quality_score
SQL;

        if (empty($query->getQuery()->columns)) {
            $query->select('products.*');
        }

        return $query->selectRaw($sql, [$marginFactor, $staleCutoff]);
    }

    public function scopeWhereQualityScoreBelow(Builder $query, int $maxScore = 60, int $staleHours = 24): Builder
    {
        return $query
            ->withQualityScore($staleHours)
            ->having('quality_score', '<=', $maxScore);
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
