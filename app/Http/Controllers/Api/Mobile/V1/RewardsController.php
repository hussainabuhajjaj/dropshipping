<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Resources\Mobile\V1\RewardSummaryResource;
use App\Http\Resources\Mobile\V1\VoucherResource;
use App\Models\Customer;
use App\Services\Account\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardsController extends ApiController
{
    public function summary(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $metadata = is_array($customer->metadata ?? null) ? $customer->metadata : [];
        $voucherCount = count(app(WalletService::class)->getVouchers($customer));

        // Stubbed loyalty points until a dedicated rewards system is implemented.
        $summary = [
            'points_balance' => (int) ($metadata['points_balance'] ?? 0),
            'tier' => $metadata['tier'] ?? 'Starter',
            'next_tier' => $metadata['next_tier'] ?? null,
            'points_to_next_tier' => (int) ($metadata['points_to_next_tier'] ?? 0),
            'progress_percent' => (int) ($metadata['progress_percent'] ?? 0),
            'voucher_count' => $voucherCount,
            'updated_at' => now(),
        ];

        return $this->success(new RewardSummaryResource($summary));
    }

    public function vouchers(Request $request): JsonResponse
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return $this->unauthorized();
        }

        $vouchers = app(WalletService::class)->getVouchers($customer);

        return $this->success(VoucherResource::collection($vouchers));
    }
}
