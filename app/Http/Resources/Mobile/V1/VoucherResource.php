<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;

class VoucherResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        $redeemedAt = $data['redeemed_at'] ?? null;

        return [
            'id' => $data['id'] ?? null,
            'code' => $data['code'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'value' => $data['value'] ?? null,
            'type' => $data['type'] ?? null,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'min_order_total' => isset($data['min_order_total']) ? (float) $data['min_order_total'] : null,
            'currency' => $data['currency'] ?? 'USD',
            'status' => $data['status'] ?? null,
            'starts_at' => $startsAt?->toIso8601String() ?? (is_string($startsAt) ? $startsAt : null),
            'ends_at' => $endsAt?->toIso8601String() ?? (is_string($endsAt) ? $endsAt : null),
            'redeemed_at' => $redeemedAt?->toIso8601String() ?? (is_string($redeemedAt) ? $redeemedAt : null),
        ];
    }
}
