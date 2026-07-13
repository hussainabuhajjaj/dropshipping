<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Customer;
use App\Models\StorefrontCampaign;
use App\Notifications\Marketing\CampaignLifecycleNotification;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class CampaignLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private StorefrontCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('en');

        $this->campaign = StorefrontCampaign::create([
            'name' => 'Summer Sale',
            'slug' => 'summer-sale',
            'type' => 'seasonal',
            'status' => 'active',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'hero_kicker' => '-20% on everything',
            'hero_subtitle' => 'Summer deals are here!',
        ]);

        $this->campaign->notification_config = [
            'started' => ['push' => true, 'email' => true, 'whatsapp' => false],
            'ending_soon' => ['push' => true, 'email' => true, 'whatsapp' => true],
            'ended' => ['push' => false, 'email' => true, 'whatsapp' => false],
        ];
        $this->campaign->save();
    }

    public function test_via_returns_push_and_email_for_started_event(): void
    {
        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $channels = $notification->via($customer);

        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains('mail', $channels);
        $this->assertNotContains(WhatsAppChannel::class, $channels);
        $this->assertContains('database', $channels);
    }

    public function test_via_includes_whatsapp_when_configured_and_customer_has_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456', 'marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ending_soon');
        $channels = $notification->via($customer);

        $this->assertContains(WhatsAppChannel::class, $channels);
        $this->assertContains(ExpoNotificationsChannel::class, $channels);
    }

    public function test_via_excludes_push_when_configured_off(): void
    {
        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ended');
        $channels = $notification->via($customer);

        $this->assertNotContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_via_respects_marketing_opt_out(): void
    {
        $customer = Customer::factory()->create(['marketing_opt_in' => false]);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $channels = $notification->via($customer);

        $this->assertEmpty($channels);
    }

    public function test_via_excludes_whatsapp_when_customer_has_no_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => null, 'marketing_opt_in' => true]);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ending_soon');
        $channels = $notification->via($customer);

        $this->assertNotContains(WhatsAppChannel::class, $channels);
    }

    public function test_via_returns_only_database_when_no_channels_configured(): void
    {
        $this->campaign->notification_config = [
            'started' => ['push' => false, 'email' => false, 'whatsapp' => false],
        ];
        $this->campaign->save();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $channels = $notification->via($customer);

        $this->assertEquals(['database'], $channels);
    }

    public function test_to_mail_english_for_started_event(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('Summer Sale is live', $mail['subject']);
        $this->assertStringContainsString('Summer deals are here', $mail['html']);
    }

    public function test_to_mail_french_for_started_event(): void
    {
        App::setLocale('fr');

        $this->campaign->locale_overrides = [
            ['locale' => 'fr', 'name' => "Soldes d'Été", 'hero_subtitle' => "Les soldes d'été sont là !"],
        ];
        $this->campaign->save();

        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString("Soldes d'Été est lancé", $mail['subject']);
        $this->assertStringContainsString("soldes d'été sont là", $mail['html']);
    }

    public function test_to_mail_for_ending_soon_event(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ending_soon');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('Last chance', $mail['subject']);
        $this->assertStringContainsString('hours left', $mail['html']);
    }

    public function test_to_mail_for_ended_event(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ended');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('has ended', $mail['subject']);
    }

    public function test_to_expo_notification_for_started_event(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Summer Sale', $message->title);
        $this->assertStringContainsString('Summer deals', $message->body);
        $this->assertEquals('campaigns', $message->channelId);
        $this->assertStringContainsString('Campaigns', $message->jsonData);
    }

    public function test_to_expo_notification_french(): void
    {
        App::setLocale('fr');
        $this->campaign->locale_overrides = [
            ['locale' => 'fr', 'name' => "Soldes d'Été"],
        ];
        $this->campaign->save();

        $customer = Customer::factory()->create(['locale' => 'fr']);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString("Soldes d'Été", $message->title);
    }

    public function test_to_whatsapp_for_ending_soon(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ending_soon');
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('Last chance', $text);
        $this->assertStringContainsString('ends soon', $text);
        $this->assertStringContainsString('/summer-sale', $text);
    }

    public function test_to_whatsapp_french(): void
    {
        App::setLocale('fr');
        $this->campaign->locale_overrides = [
            ['locale' => 'fr', 'name' => "Soldes d'Été"],
        ];
        $this->campaign->save();

        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'ending_soon');
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('Dernière chance', $text);
        $this->assertStringContainsString("Soldes d'Été", $text);
    }

    public function test_to_array_structure(): void
    {
        $customer = Customer::factory()->create(['locale' => 'en']);

        $notification = new CampaignLifecycleNotification($this->campaign, 'started');
        $data = $notification->toArray($customer);

        $this->assertEquals('campaign_started', $data['type']);
        $this->assertEquals($this->campaign->id, $data['campaign_id']);
        $this->assertEquals('summer-sale', $data['campaign_slug']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertStringContainsString('/summer-sale', $data['action_url']);
    }
}
