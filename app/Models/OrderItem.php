<?php

namespace App\Models;

use App\Domain\Orders\Models\OrderItem as DomainOrderItem;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends DomainOrderItem
{
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productVariant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domain\Products\Models\ProductVariant::class);
    }

    public function fulfillmentProvider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domain\Fulfillment\Models\FulfillmentProvider::class);
    }

    public function supplierProduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domain\Products\Models\SupplierProduct::class);
    }

    public function fulfillmentJob(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domain\Fulfillment\Models\FulfillmentJob::class);
    }

    public function shipments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Fulfillment\Models\Shipment::class);
    }

    public function fulfillmentEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Fulfillment\Models\FulfillmentEvent::class, 'order_item_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class, 'order_item_id');
    }

    public function returnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class, 'order_item_id');
    }
}
