<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;

class StatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ok' => (bool) ($this->resource['ok'] ?? false),
        ];
    }
}
