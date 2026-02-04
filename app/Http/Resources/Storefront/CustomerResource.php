<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone ?? null,
            'avatar' => is_array($this->metadata ?? null) ? ($this->metadata['avatar'] ?? null) : null,
            'country_code' => $this->country_code ?? null,
            'address_line1' => $this->address_line1 ?? null,
            'address_line2' => $this->address_line2 ?? null,
            'city' => $this->city ?? null,
            'region' => $this->region ?? null,
            'postal_code' => $this->postal_code ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
