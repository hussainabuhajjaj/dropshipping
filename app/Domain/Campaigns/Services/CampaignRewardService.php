<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Enums\LuckyDrawRewardType;
use App\Domain\Campaigns\Models\CampaignParticipation;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Str;

/**
 * Centralises guaranteed-reward issuance for campaign participation.
 * Rewards map onto the existing GiftCard / Coupon primitives so no
 * discount logic is duplicated.
 */
class CampaignRewardService
{
    public const SOURCE = 'lucky_draw';

    /**
     * Issue a guaranteed reward and return the human-visible reward code.
     *
     * @param  array<string, mixed>  $context  (campaign_id, participation_id, order_id)
     */
    public function issue(Customer $customer, LuckyDrawRewardType $type, float $value, array $context = []): string
    {
        return match ($type) {
            LuckyDrawRewardType::STORE_CREDIT => $this->issueStoreCredit($customer, $value, $context),
            LuckyDrawRewardType::FREE_SHIPPING => $this->issueCoupon($customer, 'free_shipping', 0.0, $context),
            LuckyDrawRewardType::PERCENTAGE_DISCOUNT, LuckyDrawRewardType::COUPON_CODE => $this->issueCoupon($customer, 'percentage', $value, $context),
            LuckyDrawRewardType::FIXED_DISCOUNT => $this->issueCoupon($customer, 'fixed', $value, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function issueStoreCredit(Customer $customer, float $value, array $context = []): string
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Store credit value must be positive.');
        }

        $converter = app(CurrencyConversionService::class);
        $campaignCurrency = (string) ($context['currency'] ?? 'XOF');
        $balance = $campaignCurrency === 'USD'
            ? $value
            : ($converter->convertAmount($value, $campaignCurrency, 'USD') ?? $value);

        $giftCard = GiftCard::create([
            'customer_id' => $customer->id,
            'code' => 'LUCKY-' . strtoupper(Str::random(10)),
            'balance' => round((float) $balance, 2),
            'currency' => 'USD',
            'status' => 'active',
            'expires_at' => now()->addMonths(6),
            'meta' => [
                'source' => self::SOURCE,
                'campaign_id' => $context['campaign_id'] ?? null,
                'participation_id' => $context['participation_id'] ?? null,
                'original_amount' => $value,
                'original_currency' => $campaignCurrency,
            ],
        ]);

        return $giftCard->code;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function issueCoupon(Customer $customer, string $type, float $value, array $context = []): string
    {
        if ($type === 'percentage') {
            $prefix = 'LUCKYPCT';
        } elseif ($type === 'fixed') {
            $prefix = 'LUCKYFIX';
        } else {
            $prefix = 'LUCKYFS';
        }

        $coupon = Coupon::create([
            'code' => $prefix . strtoupper(Str::random(6)),
            'description' => $this->describeReward($customer, $type, $value),
            'type' => $type,
            'amount' => $type === 'free_shipping' ? 0.0 : $value,
            'min_order_total' => null,
            'max_uses' => 1,
            'uses' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(90),
            'is_one_time_per_customer' => true,
            'applicable_to' => 'all',
            'meta' => [
                'source' => self::SOURCE,
                'campaign_id' => $context['campaign_id'] ?? null,
                'participation_id' => $context['participation_id'] ?? null,
                'customer_id' => $customer->id,
            ],
        ]);

        return $coupon->code;
    }

    /**
     * Apply an already-issued reward to a participation record (audit trail).
     *
     * @param  array<string, mixed>  $context
     */
    public function attachRewardToParticipation(CampaignParticipation $participation, string $rewardCode, array $context = []): void
    {
        $participation->issueReward($rewardCode);
    }

    private function describeReward(Customer $customer, string $type, float $value): string
    {
        return match ($type) {
            'free_shipping' => "Lucky draw free shipping reward for {$customer->email}",
            'fixed' => "Lucky draw {$value} reward for {$customer->email}",
            default => "Lucky draw {$value}% reward for {$customer->email}",
        };
    }
}
