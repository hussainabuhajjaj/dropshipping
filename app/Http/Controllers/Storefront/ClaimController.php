<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Marketing\Models\QrCampaign;
use App\Domain\Marketing\Models\QrCampaignClaim;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClaimController extends Controller
{
    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $campaign = QrCampaign::where('slug', $slug)->first();

        if (! $campaign || ! $campaign->isClaimable()) {
            return Inertia::render('Rewards/Claim', [
                'campaign' => null,
                'isLoggedIn' => Auth::guard('customer')->check(),
                'alreadyClaimed' => false,
                'justClaimed' => false,
                'autoClaim' => false,
            ]);
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $alreadyClaimed = $customer ? $campaign->hasCustomerClaimed($customer) : false;
        $autoClaim = $customer !== null && ! $alreadyClaimed && $request->query('auto_claim') === '1';
        $justClaimed = session()->pull('reward_just_claimed', false);

        return Inertia::render('Rewards/Claim', [
            'campaign' => [
                'id' => $campaign->id,
                'slug' => $campaign->slug,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'reward_type' => $campaign->reward_type,
                'reward_label' => $campaign->rewardLabel(),
                'expires_at' => $campaign->expires_at?->format('d M Y'),
            ],
            'isLoggedIn' => $customer !== null,
            'alreadyClaimed' => $alreadyClaimed,
            'justClaimed' => $justClaimed,
            'autoClaim' => $autoClaim,
        ]);
    }

    public function claim(Request $request, string $slug): RedirectResponse
    {
        $campaign = QrCampaign::where('slug', $slug)->first();

        if (! $campaign || ! $campaign->isClaimable()) {
            return back()->with('error', 'This campaign is no longer available.');
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return back()->with('error', 'You must be logged in to claim this reward.');
        }

        if ($campaign->hasCustomerClaimed($customer)) {
            return back()->with('error', 'You have already claimed this reward.');
        }

        // Create the claim
        $claim = QrCampaignClaim::create([
            'qr_campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'claimed_at' => now(),
            'meta' => [
                'reward_type' => $campaign->reward_type,
                'reward_value' => $campaign->reward_value,
                'product_id' => $campaign->product_id,
            ],
        ]);

        // Increment claim count
        $campaign->increment('claim_count');

        // Auto-deliver reward based on type
        $this->deliverReward($campaign, $customer, $claim);

        session()->flash('reward_just_claimed', true);

        return back()->with('success', 'Reward claimed successfully!');
    }

    private function deliverReward(QrCampaign $campaign, Customer $customer, QrCampaignClaim $claim): void
    {
        switch ($campaign->reward_type) {
            case 'money':
                $this->deliverMoneyReward($campaign, $customer, $claim);
                break;

            case 'product':
                $this->deliverProductReward($campaign, $customer, $claim);
                break;

            case 'points':
                $this->deliverPointsReward($campaign, $customer, $claim);
                break;
        }
    }

    private function deliverMoneyReward(QrCampaign $campaign, Customer $customer, QrCampaignClaim $claim): void
    {
        $amount = (float) ($campaign->reward_value ?? 0);
        if ($amount <= 0) {
            return;
        }

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
                'original_amount' => $amount,
                'original_currency' => $campaignCurrency,
            ],
        ]);

        $claim->update([
            'reward_delivered' => true,
            'reward_delivered_at' => now(),
        ]);
    }

    private function deliverProductReward(QrCampaign $campaign, Customer $customer, QrCampaignClaim $claim): void
    {
        $claim->update([
            'meta' => array_merge($claim->meta ?? [], [
                'product_name' => $campaign->product?->name,
                'product_id' => $campaign->product_id,
                'status' => 'pending_fulfillment',
            ]),
        ]);
    }

    private function deliverPointsReward(QrCampaign $campaign, Customer $customer, QrCampaignClaim $claim): void
    {
        $claim->update([
            'reward_delivered' => true,
            'reward_delivered_at' => now(),
            'meta' => array_merge($claim->meta ?? [], [
                'points_awarded' => (float) ($campaign->reward_value ?? 0),
                'status' => 'pending_manual',
            ]),
        ]);
    }
}
