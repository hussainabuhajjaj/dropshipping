<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company' => $this->company ?? null,
            'phone' => $this->phone ?? null,
            'address' => $this->address ?? null,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'zip' => $this->zip ?? null,
            'country' => $this->country ?? null,
            'website' => $this->website ?? null,
            'rating' => $this->rating ?? 0,
            'lead_time_days' => $this->lead_time_days ?? 7,
            'minimum_order_qty' => $this->minimum_order_qty ?? 1,
            'status' => $this->status,
            'product_count' => $this->whenLoaded('products', $this->products->count()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
