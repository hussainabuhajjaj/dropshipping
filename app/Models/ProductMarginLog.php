<?php

namespace App\Models;

use App\Domain\Products\Models\Product as DomainProduct;
use App\Domain\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class ProductMarginLog extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'source',
        'event',
        'actor_type',
        'actor_id',
        'old_margin_percent',
        'new_margin_percent',
        'old_selling_price',
        'new_selling_price',
        'old_status',
        'new_status',
        'sales_count',
        'notes',
    ];

    protected $casts = [
        'old_margin_percent' => 'decimal:2',
        'new_margin_percent' => 'decimal:2',
        'old_selling_price' => 'decimal:2',
        'new_selling_price' => 'decimal:2',
        'sales_count' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(DomainProduct::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
