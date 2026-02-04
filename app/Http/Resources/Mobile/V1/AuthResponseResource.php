<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;

class AuthResponseResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        return [
            'user' => new CustomerResource($this->resource['user']),
            'token' => $this->resource['token'] ?? null,
            'token_type' => $this->resource['token_type'] ?? 'Bearer',
            'expires_at' => $this->resource['expires_at'] ?? null,
        ];
    }
}
