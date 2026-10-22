<?php

namespace App\Models;

use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Services\PricingService;
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
        // Try variant price first
        $price = (float)($this?->variant?->price ?? 0);
        
        // If no variant price, try product selling price
        if ($price <= 0) {
            $price = (float)($this?->product?->selling_price ?? 0);
        }
        
        // If still no price, try to calculate from cost price with minimum margin
        if ($price <= 0) {
            $costPrice = (float)($this?->variant?->cost_price ?? $this?->product?->cost_price ?? 0);
            if ($costPrice > 0) {
                $pricingService = app(PricingService::class);
                $price = $this?->variant
                    ? $pricingService->minimumPriceForVariant($this->variant, $this->product, $costPrice)
                    : ($this?->product
                        ? $pricingService->minimumPriceForProduct($this->product, $costPrice)
                        : $pricingService->minSellingPrice($costPrice, 'USD'));
            }
        }
        
        // Final fallback to prevent $0 pricing
        if ($price <= 0) {
            $price = 9.99; // Default minimum price
            
            // Log this issue for debugging
            \Log::warning('Cart item with $0 price - using fallback', [
                'cart_item_id' => $this->id,
                'product_id' => $this->product_id,
                'variant_id' => $this->variant_id,
                'variant_price' => $this?->variant?->price,
                'product_selling_price' => $this?->product?->selling_price,
                'variant_cost_price' => $this?->variant?->cost_price,
                'product_cost_price' => $this?->product?->cost_price,
                'fallback_price' => $price,
            ]);
        }
        
        return $price;
    }


}
