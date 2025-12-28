<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->number, // 'number' field in database
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax_total, // 'tax_total' field in database
            'shipping' => $this->shipping_total, // 'shipping_total' field in database
            'total' => $this->grand_total, // 'grand_total' field in database
            'notes' => $this->delivery_notes ?? null, // 'delivery_notes' field in database
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')), // Use 'orderItems' relationship
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
