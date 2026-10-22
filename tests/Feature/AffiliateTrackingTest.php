<?php

namespace Tests\Feature;

use App\Domain\Affiliates\Models\AffiliateCommission;
use App\Domain\Affiliates\Models\AffiliateReferral;
use App\Events\Orders\OrderPlaced;
use App\Mail\AffiliateCommissionEarned;
use App\Models\Order;
use App\Models\OrderItem;
use App\Domain\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AffiliateTrackingTest extends TestCase
{
    public function test_referral_middleware_sets_cookie_and_session(): void
    {
        $affiliate = Affiliate::factory()->create(['status' => 'approved']);

        $response = $this->get('/?ref=' . $affiliate->referral_code);

        $response->assertCookie('affiliate_referral');
        $this->assertEquals($affiliate->referral_code, session('affiliate_referral_code'));
        $this->assertNotNull(session('affiliate_referral_token'));

        $this->assertDatabaseHas('affiliate_referrals', [
            'affiliate_id' => $affiliate->id,
            'visitor_token' => session('affiliate_referral_token'),
        ]);
    }

    public function test_order_placed_generates_commissions_and_emails(): void
    {
        Mail::fake();

        $affiliate = Affiliate::factory()->create(['status' => 'approved', 'commission_rate' => 0.25]);
        $referral = AffiliateReferral::query()->create([
            'affiliate_id' => $affiliate->id,
            'visitor_token' => 'token-abc',
            'expires_at' => now()->addHour(),
        ]);

        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

        Session::put('affiliate_referral_token', $referral->visitor_token);
        Session::put('affiliate_referral_code', $affiliate->referral_code);
        Session::put('affiliate_referral_processed', false);

        event(new OrderPlaced($order));

        $this->assertDatabaseCount('affiliate_commissions', 2);
        $this->assertTrue(session('affiliate_referral_processed'));
        $this->assertNull(session('affiliate_referral_token'));

        Mail::assertSent(AffiliateCommissionEarned::class, 2);
    }
}
