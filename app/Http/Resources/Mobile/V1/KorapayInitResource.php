<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KorapayInitResource extends JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->resource['reference'] ?? null,
            'checkout_url' => $this->resource['checkout_url'] ?? null,
        ];
    }
}
