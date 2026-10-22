<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Models\SupplierProduct;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'fulfillment_provider_id',
        'supplier_product_id',
        'fulfillment_status',
        'quantity',
        'unit_price',
        'total',
        'source_sku',
        'snapshot',
        'meta',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function fulfillmentProvider(): BelongsTo
    {
        return $this->belongsTo(FulfillmentProvider::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function fulfillmentJob(): HasOne
    {
        return $this->hasOne(FulfillmentJob::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function fulfillmentEvents(): HasMany
    {
        return $this->hasMany(FulfillmentEvent::class, 'order_item_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class, 'order_item_id');
    }

    public function returnRequest(): HasOne
    {
        return $this->hasOne(\App\Models\ReturnRequest::class, 'order_item_id');
    }
}
