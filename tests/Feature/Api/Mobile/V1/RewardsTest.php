<?php

namespace Tests\Feature\Api\Mobile\V1;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\GiftCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewards_summary_shape(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/mobile/v1/rewards/summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'points_balance',
                    'tier',
                    'points_to_next_tier',
                    'voucher_count',
                ],
            ]);
    }

    public function test_wallet_returns_gift_cards_and_vouchers(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($customer);

        GiftCard::create([
            'customer_id' => $customer->id,
            'code' => 'GC-TEST',
            'balance' => 25.50,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $coupon = Coupon::create([
            'code' => 'WELCOME10',
            'description' => 'Welcome reward',
            'type' => 'percent',
            'amount' => 10,
            'is_active' => true,
        ]);

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customer->id,
            'status' => 'saved',
        ]);

        $wallet = $this->getJson('/api/mobile/v1/wallet');
        $wallet->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'gift_cards',
                    'saved_coupons',
                    'available_coupons',
                ],
            ]);

        $vouchers = $this->getJson('/api/mobile/v1/rewards/vouchers');
        $vouchers->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'code', 'value', 'status'],
                ],
            ]);
    }
}
