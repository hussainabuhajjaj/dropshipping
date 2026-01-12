<?php

namespace App\Models;

use App\Domain\Products\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'product_id', 'fulfillment_provider_id', 'variant_id', 'quantity', 'stock_on_hand'
    ];

    protected $with = ['product', 'variant'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }


    public function getSinglePrice(): float
    {
        $price = (float)($this?->variant?->price ?? ($this?->product?->selling_price ?? 0));
        return $price;
    }


}
