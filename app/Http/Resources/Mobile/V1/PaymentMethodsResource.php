<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodsResource extends JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type ?? 'card',
            'name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            'is_default' => (bool) $this->is_default,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'last_four' => $this->last_four,
            'brand' => $this->brand,
            'expiry_month' => $this->expiry_month,
            'expiry_year' => $this->expiry_year,
        ];
    }

    private function getDisplayName(): string
    {
        if ($this->type === 'card' && $this->brand && $this->last_four) {
            return strtoupper($this->brand) . ' •••• ' . $this->last_four;
        }

        return $this->name ?? $this->type ?? 'Payment Method';
    }

    private function getDescription(): ?string
    {
        if ($this->type === 'card' && $this->expiry_month && $this->expiry_year) {
            return 'Expires ' . str_pad((string) $this->expiry_month, 2, '0', STR_PAD_LEFT) . '/' . substr((string) $this->expiry_year, -2);
        }

        return $this->description;
    }
}
