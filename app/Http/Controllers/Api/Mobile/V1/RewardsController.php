<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Marketing\Models\QrCampaign;
use App\Domain\Marketing\Models\QrCampaignClaim;
use App\Http\Controllers\Storefront\ClaimController as WebClaimController;
use App\Http\Resources\Mobile\V1\RewardSummaryResource;
use App\Http\Resources\Mobile\V1\VoucherResource;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Services\Account\WalletService;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    public function showClaim(string $slug): JsonResponse
    {
        $campaign = QrCampaign::where('slug', $slug)->first();

        if (! $campaign || ! $campaign->isClaimable()) {
            return $this->error('Campaign not found or no longer available.', 404);
        }

        $customer = Auth::guard('customer')->user();
        $alreadyClaimed = $customer ? $campaign->hasCustomerClaimed($customer) : false;

        return $this->success([
            'campaign' => [
                'id' => $campaign->id,
                'slug' => $campaign->slug,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'reward_type' => $campaign->reward_type,
                'reward_label' => $campaign->rewardLabel(),
                'expires_at' => $campaign->expires_at?->format('d M Y'),
            ],
            'is_logged_in' => $customer !== null,
            'already_claimed' => $alreadyClaimed,
        ]);
    }

    public function claim(Request $request, string $slug): JsonResponse
    {
        $campaign = QrCampaign::where('slug', $slug)->first();

        if (! $campaign || ! $campaign->isClaimable()) {
            return $this->error('Campaign not found or no longer available.', 404);
        }

        $customer = $request->user();
        if (! $customer) {
            return $this->unauthorized();
        }

        if ($campaign->hasCustomerClaimed($customer)) {
            return $this->error('You have already claimed this reward.', 409);
        }

        $claim = QrCampaignClaim::create([
            'qr_campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'claimed_at' => now(),
            'meta' => [
                'reward_type' => $campaign->reward_type,
                'reward_value' => $campaign->reward_value,
                'product_id' => $campaign->product_id,
                'channel' => 'mobile_app',
            ],
        ]);

        $campaign->increment('claim_count');

        $rewardDelivered = false;
        if ($campaign->reward_type === 'money') {
            $amount = (float) ($campaign->reward_value ?? 0);
            if ($amount > 0) {
                $converter = app(CurrencyConversionService::class);
                $campaignCurrency = $campaign->meta['currency'] ?? 'XOF';
                $balance = $campaignCurrency === 'USD' ? $amount : ($converter->convertAmount($amount, $campaignCurrency, 'USD') ?? $amount);

                GiftCard::create([
                    'customer_id' => $customer->id,
                    'code' => 'RWD-' . strtoupper(Str::random(10)),
                    'balance' => $balance,
                    'currency' => 'USD',
                    'status' => 'active',
                    'expires_at' => now()->addMonths(6),
                    'meta' => [
                        'source' => 'qr_campaign',
                        'campaign_id' => $campaign->id,
                        'claim_id' => $claim->id,
                        'channel' => 'mobile_app',
                        'original_amount' => $amount,
                        'original_currency' => $campaignCurrency,
                    ],
                ]);

                $rewardDelivered = true;
                $claim->update([
                    'reward_delivered' => true,
                    'reward_delivered_at' => now(),
                ]);
            }
        }

        if ($campaign->reward_type === 'product') {
            $claim->update([
                'meta' => array_merge($claim->meta ?? [], [
                    'product_name' => $campaign->product?->name,
                    'product_id' => $campaign->product_id,
                    'status' => 'pending_fulfillment',
                ]),
            ]);
        }

        if ($campaign->reward_type === 'points') {
            $claim->update([
                'reward_delivered' => true,
                'reward_delivered_at' => now(),
                'meta' => array_merge($claim->meta ?? [], [
                    'points_awarded' => (float) ($campaign->reward_value ?? 0),
                    'status' => 'pending_manual',
                ]),
            ]);
        }

        return $this->success([
            'claimed' => true,
            'reward_delivered' => $rewardDelivered,
            'campaign_title' => $campaign->title,
            'reward_label' => $campaign->rewardLabel(),
        ]);
    }
}
