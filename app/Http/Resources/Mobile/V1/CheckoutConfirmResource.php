<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutConfirmResource extends JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->resource['order_number'] ?? null,
            'payment_reference' => $this->resource['payment_reference'] ?? null,
        ];
    }
}
