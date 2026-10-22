<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'fulfillment_provider_id',
        'external_product_id',
        'external_sku',
        'cost_price',
        'currency',
        'lead_time_days',
        'shipping_options',
        'is_active',
    ];

    protected $casts = [
        'shipping_options' => 'array',
        'is_active' => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function fulfillmentProvider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class);
    }
}
