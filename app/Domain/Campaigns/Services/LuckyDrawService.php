<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Enums\LuckyDrawParticipantState;
use App\Domain\Campaigns\Enums\LuckyDrawPrizeType;
use App\Domain\Campaigns\Enums\LuckyDrawRewardType;
use App\Domain\Campaigns\Models\CampaignParticipation;
use App\Domain\Campaigns\Models\CampaignWinner;
use App\Domain\Orders\Models\Order;
use App\Models\Customer;
use App\Models\StorefrontCampaign;
use Illuminate\Support\Facades\DB;

/**
 * Race-safe lucky draw participation + draw logic.
 *
 * Spot assignment serialises on a pessimistic row lock of the campaign
 * so concurrent webhooks can never over-allocate the participant cap.
 */
class LuckyDrawService
{
    public function __construct(
        private readonly CampaignRewardService $rewards,
    ) {
    }

    /**
     * @return array<int, CampaignParticipation> Participations created/updated.
     */
    public function registerQualifiedOrder(Order $order): array
    {
        $campaigns = $this->qualifyingCampaigns();

        $created = [];

        foreach ($campaigns as $campaign) {
            $customer = $this->resolveCustomer($order);

            if (! $customer) {
                continue;
            }

            if (! $this->orderQualifies($campaign, $order)) {
                continue;
            }

            $created[] = $this->registerParticipation($campaign, $customer, $order);
        }

        return $created;
    }

    /**
     * All lucky-draw campaigns currently accepting paid orders.
     *
     * @return \Illuminate\Support\Collection<int, StorefrontCampaign>
     */
    public function qualifyingCampaigns(): \Illuminate\Support\Collection
    {
        return StorefrontCampaign::query()
            ->where('type', 'lucky_draw')
            ->get()
            ->filter(fn (StorefrontCampaign $campaign) => $campaign->isAcceptingLuckyDrawEntries());
    }

    /**
     * Active lucky-draw campaign for display (single draw). Null when none.
     */
    public function activeLuckyDraw(?string $locale = null): ?StorefrontCampaign
    {
        return StorefrontCampaign::query()
            ->where('type', 'lucky_draw')
            ->get()
            ->filter(fn (StorefrontCampaign $campaign) => $campaign->isActiveForLocale($locale))
            ->sortByDesc('priority')
            ->first();
    }

    public function orderQualifies(StorefrontCampaign $campaign, Order $order): bool
    {
        if ($order->payment_status !== 'paid') {
            return false;
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return false;
        }

        if ((float) ($order->subtotal ?? 0) < (float) $campaign->luckyDrawConfig()['min_order_amount']) {
            return false;
        }

        return true;
    }

    /**
     * Transactionally register (or update) a participation for a customer.
     * Guarantees at most one spot per customer via the unique index and the
     * campaign row lock.
     */
    public function registerParticipation(StorefrontCampaign $campaign, Customer $customer, ?Order $order = null): CampaignParticipation
    {
        return DB::transaction(function () use ($campaign, $customer, $order): CampaignParticipation {
            // Serialise all spot assignment on the campaign row.
            StorefrontCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();

            $config = $campaign->luckyDrawConfig();
            $maxParticipants = (int) ($config['max_participants'] ?? 0);

            $participation = CampaignParticipation::query()->firstOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'customer_id' => $customer->id,
                ],
                [
                    'state' => LuckyDrawParticipantState::QUALIFIED->value,
                    'qualified_at' => now(),
                    'guaranteed_reward_type' => $config['guaranteed_reward_type'] ?? null,
                    'guaranteed_reward_value' => $config['guaranteed_reward_value'] ?? null,
                    'meta' => [
                        'campaign_name' => $campaign->name,
                        'min_order_amount' => $config['min_order_amount'] ?? null,
                        'currency' => $config['currency'] ?? null,
                    ],
                ]
            );

            if ($order) {
                $participation->orders()->syncWithoutDetaching([
                    $order->id => [
                        'order_total' => (float) ($order->subtotal ?? 0),
                        'qualified_at' => now(),
                    ],
                ]);
            }

            if (! $participation->hasReservedSpot() && $maxParticipants > 0) {
                $spotsTaken = CampaignParticipation::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereNotNull('spot_number')
                    ->count();

                if ($spotsTaken < $maxParticipants) {
                    $participation->markSpotReserved($spotsTaken + 1);
                }
            }

            return $participation;
        });
    }

    /**
     * Run the random draw after the campaign ends.
     *
     * @return array{campaign: StorefrontCampaign, grand: ?CampaignWinner, runner_ups: \Illuminate\Support\Collection<int, CampaignWinner>}
     */
    public function runDraw(StorefrontCampaign $campaign): array
    {
        return DB::transaction(function () use ($campaign): array {
            StorefrontCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();

            if (! $campaign->winners()->exists()) {
                $config = $campaign->luckyDrawConfig();
                $participants = $campaign->participations()
                    ->whereNotNull('spot_number')
                    ->whereNull('deleted_at')
                    ->inRandomOrder()
                    ->get();

                $grandWinner = $participants->shift();

                if ($grandWinner) {
                    $this->createWinner($campaign, $grandWinner, LuckyDrawPrizeType::GRAND, (string) ($config['grand_prize'] ?? 'Grand Prize'));
                }

                $runnerUpCount = min((int) ($config['runner_up_count'] ?? 0), $participants->count());
                $runnerUps = $participants->take($runnerUpCount);

                foreach ($runnerUps as $runnerUp) {
                    $this->createWinner($campaign, $runnerUp, LuckyDrawPrizeType::RUNNER_UP, $this->runnerUpLabel($config));
                }
            }

            return [
                'campaign' => $campaign->refresh(),
                'grand' => $campaign->winners()->where('prize_type', LuckyDrawPrizeType::GRAND->value)->first(),
                'runner_ups' => $campaign->winners()->where('prize_type', LuckyDrawPrizeType::RUNNER_UP->value)->get(),
            ];
        });
    }

    /**
     * Issue guaranteed rewards to every qualified participant who did not win
     * the grand prize. Idempotent (skips participations already rewarded).
     *
     * @return int Number of rewards issued.
     */
    public function issueGuaranteedRewards(StorefrontCampaign $campaign): int
    {
        $config = $campaign->luckyDrawConfig();
        $rewardType = LuckyDrawRewardType::tryFrom((string) ($config['guaranteed_reward_type'] ?? ''))
            ?? LuckyDrawRewardType::COUPON_CODE;
        $rewardValue = (float) ($config['guaranteed_reward_value'] ?? 0);

        $issued = 0;

        $campaign->participations()
            ->whereNotNull('spot_number')
            ->whereNull('deleted_at')
            ->whereNull('reward_code')
            ->chunkById(200, function ($participations) use ($campaign, $config, $rewardType, $rewardValue, &$issued): void {
                /** @var CampaignParticipation $participation */
                foreach ($participations as $participation) {
                    $isGrandWinner = $campaign->winners()
                        ->where('participation_id', $participation->id)
                        ->where('prize_type', LuckyDrawPrizeType::GRAND->value)
                        ->exists();

                    if ($isGrandWinner) {
                        continue;
                    }

                    $code = $this->rewards->issue(
                        $participation->customer,
                        $rewardType,
                        $rewardValue,
                        [
                            'campaign_id' => $campaign->id,
                            'participation_id' => $participation->id,
                            'currency' => $config['currency'] ?? 'XOF',
                        ]
                    );

                    $participation->issueReward($code, LuckyDrawParticipantState::REWARD_ISSUED);

                    $this->createWinner($campaign, $participation, LuckyDrawPrizeType::GUARANTEED, $this->rewardLabel($rewardType, $rewardValue, $config), $code);

                    $issued++;
                }
            });

        return $issued;
    }

    /**
     * Mark all winners as announced (sets announced_at). Sends notifications
     * via the campaign notification listener when wired.
     *
     * @return int Number of winners announced.
     */
    public function announceWinners(StorefrontCampaign $campaign): int
    {
        $announced = 0;

        $campaign->winners()
            ->whereNull('announced_at')
            ->chunkById(200, function ($winners) use (&$announced): void {
                foreach ($winners as $winner) {
                    $winner->announce();
                    $announced++;
                }
            });

        return $announced;
    }

    public function isParticipant(StorefrontCampaign $campaign, Customer $customer): bool
    {
        return CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->where('customer_id', $customer->id)
            ->exists();
    }

    public function participationFor(StorefrontCampaign $campaign, Customer $customer): ?CampaignParticipation
    {
        return CampaignParticipation::query()
            ->where('campaign_id', $campaign->id)
            ->where('customer_id', $customer->id)
            ->first();
    }

    private function createWinner(StorefrontCampaign $campaign, CampaignParticipation $participation, LuckyDrawPrizeType $type, string $label, ?string $rewardCode = null): CampaignWinner
    {
        $winner = CampaignWinner::query()->firstOrCreate(
            [
                'campaign_id' => $campaign->id,
                'participation_id' => $participation->id,
            ],
            [
                'customer_id' => $participation->customer_id,
                'prize_type' => $type->value,
                'prize_value' => $this->prizeValue($type, $campaign->luckyDrawConfig()),
                'prize_label' => $label,
                'reward_code' => $rewardCode,
                'status' => 'pending',
            ]
        );

        $participation->forceFill(['state' => LuckyDrawParticipantState::WINNER->value])->save();

        return $winner;
    }

    private function prizeValue(LuckyDrawPrizeType $type, array $config): ?float
    {
        return match ($type) {
            LuckyDrawPrizeType::RUNNER_UP => (float) ($config['gift_card_amount'] ?? 0),
            LuckyDrawPrizeType::GUARANTEED => (float) ($config['guaranteed_reward_value'] ?? 0),
            LuckyDrawPrizeType::GRAND => null,
        };
    }

    private function runnerUpLabel(array $config): string
    {
        $amount = (float) ($config['gift_card_amount'] ?? 20);
        $currency = (string) ($config['gift_card_currency'] ?? 'USD');

        return $currency === 'XOF'
            ? "{$amount} FCFA SIMBAZU Gift Card"
            : "\${$amount} SIMBAZU Gift Card";
    }

    private function rewardLabel(LuckyDrawRewardType $type, float $value, array $config): string
    {
        return match ($type) {
            LuckyDrawRewardType::FREE_SHIPPING => 'Free Shipping Reward',
            LuckyDrawRewardType::STORE_CREDIT => 'Store Credit Reward',
            LuckyDrawRewardType::PERCENTAGE_DISCOUNT, LuckyDrawRewardType::COUPON_CODE => "{$value}% Discount Reward",
            LuckyDrawRewardType::FIXED_DISCOUNT => "{$value} Discount Reward",
        };
    }

    private function resolveCustomer(Order $order): ?Customer
    {
        if ($order->customer_id && $order->customer) {
            return $order->customer;
        }

        if ($order->email) {
            $byEmail = Customer::query()->where('email', $order->email)->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        return null;
    }
}
