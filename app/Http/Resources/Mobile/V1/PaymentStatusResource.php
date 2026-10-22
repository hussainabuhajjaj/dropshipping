<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentStatusResource extends JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        return [
            'payment_status' => $this->resource['payment_status'] ?? null,
            'order_status' => $this->resource['order_status'] ?? null,
            'order_number' => $this->resource['order_number'] ?? null,
            'reference' => $this->resource['reference'] ?? null,
            'is_paid' => (bool) ($this->resource['is_paid'] ?? false),
            'amount' => $this->resource['amount'] ?? null,
            'currency' => $this->resource['currency'] ?? null,
            'paid_at' => $this->resource['paid_at'] ?? null,
        ];
    }
}
