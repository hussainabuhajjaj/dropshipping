<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Models;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceProductMap extends Model
{
    protected $table = 'woocommerce_product_maps';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'woocommerce_product_id',
        'woocommerce_variation_id',
        'sku',
        'sync_hash',
        'status',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'woocommerce_product_id' => 'integer',
        'woocommerce_variation_id' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
