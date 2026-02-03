<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        return [
            'lines' => CartItemResource::collection($this->resource['lines'] ?? []),
            'currency' => $this->resource['currency'] ?? 'USD',
            'subtotal' => (float) ($this->resource['subtotal'] ?? 0),
            'shipping' => (float) ($this->resource['shipping'] ?? 0),
            'discount' => (float) ($this->resource['discount'] ?? 0),
            'tax' => (float) ($this->resource['tax'] ?? 0),
            'total' => (float) ($this->resource['total'] ?? 0),
            'coupon' => $this->resource['coupon'] ?? null,
            'discount_label' => $this->resource['discount_label'] ?? null,
            'applied_promotions' => $this->resource['applied_promotions'] ?? [],
            'minimum_cart_requirement' => $this->resource['minimum_cart_requirement'] ?? null,
        ];
    }
}
