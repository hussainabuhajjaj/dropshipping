<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cj_vid',
        'cj_variant_data',
        'cj_stock',
        'cj_stock_synced_at',
        'sku',
        'title',
        'price',
        'compare_at_price',
        'cost_price',
        'currency',
        'weight_grams',
        'package_length_mm',
        'package_width_mm',
        'package_height_mm',
        'inventory_policy',
        'stock_on_hand',
        'low_stock_threshold',
        'options',
        'metadata',
        'variant_image'
    ];

    protected $casts = [
        'options' => 'array',
        'cj_variant_data' => 'array',
        'cj_stock_synced_at' => 'datetime',
        'metadata' => 'array',
        'stock_on_hand' => 'integer',
        'low_stock_threshold' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
