<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;

class AuthResponseResource extends JsonResource
{
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
