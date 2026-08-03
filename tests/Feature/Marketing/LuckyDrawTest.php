<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Domain\Campaigns\Enums\LuckyDrawParticipantState;
use App\Domain\Campaigns\Enums\LuckyDrawPrizeType;
use App\Domain\Campaigns\Services\LuckyDrawService;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StorefrontCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LuckyDrawTest extends TestCase
{
    use RefreshDatabase;

    private StorefrontCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('en');
        config(['campaigns.lucky_draw.enabled' => true]);

        $this->campaign = StorefrontCampaign::create([
            'name' => 'iPhone 17 Lucky Draw',
            'slug' => 'iphone-17-lucky-draw',
            'type' => 'lucky_draw',
            'status' => 'active',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'hero_kicker' => 'Win an iPhone 17 Pro Max',
            'hero_subtitle' => 'Every qualifying order enters the draw.',
            'lucky_draw_config' => [
                'min_order_amount' => 30000,
                'currency' => 'XOF',
                'max_participants' => 50,
                'grand_prize' => 'iPhone 17 Pro Max',
                'runner_up_count' => 3,
                'gift_card_amount' => 20,
                'gift_card_currency' => 'USD',
                'guaranteed_reward_type' => 'coupon_code',
                'guaranteed_reward_value' => 10,
                'show_remaining_spots' => true,
                'countdown_enabled' => true,
            ],
        ]);
    }

    private function order(Customer $customer, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'subtotal' => 40000,
            'payment_status' => 'paid',
            'status' => 'processing',
            'currency' => 'XOF',
        ], $overrides));
    }

    public function test_qualifying_paid_order_registers_participation_with_spot(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer);

        $created = app(LuckyDrawService::class)->registerQualifiedOrder($order);

        $this->assertCount(1, $created);
        $this->assertDatabaseHas('campaign_participations', [
            'campaign_id' => $this->campaign->id,
            'customer_id' => $customer->id,
            'spot_number' => 1,
            'state' => LuckyDrawParticipantState::SPOT_RESERVED->value,
        ]);
        $this->assertDatabaseHas('campaign_participation_orders', [
            'order_id' => $order->id,
        ]);
    }

    public function test_order_below_minimum_is_not_registered(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, ['subtotal' => 5000]);

        $created = app(LuckyDrawService::class)->registerQualifiedOrder($order);

        $this->assertCount(0, $created);
        $this->assertDatabaseMissing('campaign_participations', ['customer_id' => $customer->id]);
    }

    public function test_unpaid_and_cancelled_orders_are_not_registered(): void
    {
        $customer = Customer::factory()->create();

        $unpaid = $this->order($customer, ['payment_status' => 'pending']);
        $cancelled = $this->order($customer, ['status' => 'cancelled']);

        $service = app(LuckyDrawService::class);

        $this->assertCount(0, $service->registerQualifiedOrder($unpaid));
        $this->assertCount(0, $service->registerQualifiedOrder($cancelled));
        $this->assertDatabaseMissing('campaign_participations', ['customer_id' => $customer->id]);
    }

    public function test_at_most_one_spot_per_customer(): void
    {
        $customer = Customer::factory()->create();
        $service = app(LuckyDrawService::class);

        $service->registerQualifiedOrder($this->order($customer));
        $service->registerQualifiedOrder($this->order($customer));

        $this->assertDatabaseCount('campaign_participations', 1);
        $this->assertDatabaseCount('campaign_participation_orders', 2);
    }

    public function test_concurrent_registration_never_exceeds_participant_cap(): void
    {
        $this->campaign->update(['lucky_draw_config' => array_merge($this->campaign->luckyDrawConfig(), ['max_participants' => 3])]);

        $service = app(LuckyDrawService::class);
        $customers = Customer::factory()->count(6)->create();

        foreach ($customers as $customer) {
            $service->registerQualifiedOrder($this->order($customer));
        }

        $this->assertDatabaseCount('campaign_participations', 6);

        $spots = $this->campaign->participations()->whereNotNull('spot_number')->count();
        $this->assertEquals(3, $spots);
    }

    public function test_draw_selects_one_grand_and_configured_runner_ups(): void
    {
        $service = app(LuckyDrawService::class);
        $customers = Customer::factory()->count(6)->create();

        foreach ($customers as $customer) {
            $service->registerQualifiedOrder($this->order($customer));
        }

        $result = $service->runDraw($this->campaign);

        $this->assertNotNull($result['grand']);
        $this->assertEquals(LuckyDrawPrizeType::GRAND->value, $result['grand']->prize_type);
        $this->assertCount(3, $result['runner_ups']);
        $this->assertEquals(4, $this->campaign->winners()->count());
    }

    public function test_draw_is_idempotent(): void
    {
        $service = app(LuckyDrawService::class);
        $customers = Customer::factory()->count(5)->create();

        foreach ($customers as $customer) {
            $service->registerQualifiedOrder($this->order($customer));
        }

        $service->runDraw($this->campaign);
        $service->runDraw($this->campaign);

        $this->assertEquals(4, $this->campaign->winners()->count());
    }

    public function test_guaranteed_rewards_are_issued_to_non_grand_winners(): void
    {
        $service = app(LuckyDrawService::class);
        $customers = Customer::factory()->count(6)->create();

        foreach ($customers as $customer) {
            $service->registerQualifiedOrder($this->order($customer));
        }

        $service->runDraw($this->campaign);
        $issued = $service->issueGuaranteedRewards($this->campaign);

        $this->assertEquals(5, $issued);

        $grand = $this->campaign->winners()->where('prize_type', LuckyDrawPrizeType::GRAND->value)->first();
        $grandParticipation = $grand->participation;
        $this->assertNull($grandParticipation->reward_code);

        $rewarded = $this->campaign->participations()
            ->whereNotNull('reward_code')
            ->count();
        $this->assertEquals(5, $rewarded);
    }

    public function test_announce_winners_marks_announced_at(): void
    {
        $service = app(LuckyDrawService::class);
        $customers = Customer::factory()->count(5)->create();

        foreach ($customers as $customer) {
            $service->registerQualifiedOrder($this->order($customer));
        }

        $service->runDraw($this->campaign);
        $count = $service->announceWinners($this->campaign);

        $this->assertEquals(4, $count);
        $this->assertSame(4, $this->campaign->winners()->whereNotNull('announced_at')->count());
    }

    public function test_feature_flag_disables_registration(): void
    {
        config(['campaigns.lucky_draw.enabled' => false]);

        $customer = Customer::factory()->create();
        $created = app(LuckyDrawService::class)->registerQualifiedOrder($this->order($customer));

        $this->assertCount(0, $created);
        $this->assertDatabaseMissing('campaign_participations', ['customer_id' => $customer->id]);
    }

    public function test_campaign_web_page_includes_lucky_draw_payload(): void
    {
        $customer = Customer::factory()->create();
        $service = app(LuckyDrawService::class);
        $service->registerQualifiedOrder($this->order($customer));

        $response = $this->get('/campaigns/iphone-17-lucky-draw');
        $response->assertOk();

        $props = json_decode(json_encode($response->viewData('page')), true)['props'] ?? [];

        $this->assertArrayHasKey('lucky_draw', $props);
        $this->assertEquals(30000, $props['lucky_draw']['min_order_amount']);
        $this->assertEquals('iPhone 17 Pro Max', $props['lucky_draw']['grand_prize']);
        $this->assertEquals(1, $props['lucky_draw']['spots_filled']);
        $this->assertArrayHasKey('products', $props);
    }

    public function test_storefront_shared_lucky_draw_prop_exposes_base_currency_threshold(): void
    {
        config(['currency.rates' => [
            'USD_XOF' => 600,
            'XOF_USD' => 0.00167,
        ]]);

        $response = $this->get('/');
        $response->assertOk();

        $props = json_decode(json_encode($response->viewData('page')), true)['props'] ?? [];

        $this->assertArrayHasKey('luckyDraw', $props);
        $this->assertEquals(30000, $props['luckyDraw']['min_order_amount']);
        $this->assertEquals('XOF', $props['luckyDraw']['currency']);
        // Cart/checkout/product prices are USD; the threshold must be exposed
        // in USD too so frontend comparisons are not mixing currencies.
        $this->assertEqualsWithDelta(50.1, $props['luckyDraw']['min_order_amount_usd'], 0.01);
    }
}
