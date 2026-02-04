<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;

class RewardSummaryResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'points_balance' => (int) ($data['points_balance'] ?? 0),
            'tier' => $data['tier'] ?? 'Starter',
            'next_tier' => $data['next_tier'] ?? null,
            'points_to_next_tier' => (int) ($data['points_to_next_tier'] ?? 0),
            'progress_percent' => (int) ($data['progress_percent'] ?? 0),
            'voucher_count' => (int) ($data['voucher_count'] ?? 0),
            'updated_at' => $data['updated_at']?->toIso8601String() ?? null,
        ];
    }
}
