<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\AbandonedCart;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Notifications\AbandonedCartNotification;
use App\Jobs\SendAbandonedCartReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;
use App\Notifications\Channels\WhatsAppChannel;

class AbandonedCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('en');

        if (DB::connection()->getDriverName() === 'sqlite' && ! Schema::hasColumn('products', 'searchable_text')) {
            Schema::table('products', function ($table): void {
                $table->text('searchable_text')->nullable();
            });
        }
    }

    private function createCart(array $overrides = []): AbandonedCart
    {
        return AbandonedCart::create(array_merge([
            'session_id' => 'test-session-' . Str::random(8),
            'email' => 'test@example.com',
            'cart_data' => [],
            'abandoned_at' => now()->subHours(2),
        ], $overrides));
    }

    public function test_notification_via_returns_all_channels_for_registered_customer(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456', 'marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $cart = $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'cart_data' => [['name' => 'Phone Case', 'quantity' => 1, 'price' => 15.00]],
        ]);

        $notification = new AbandonedCartNotification($cart);

        $channels = $notification->via($customer);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_notification_via_respects_marketing_opt_out(): void
    {
        $customer = Customer::factory()->create(['marketing_opt_in' => false]);
        $cart = $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);

        $notification = new AbandonedCartNotification($cart);
        $channels = $notification->via($customer);

        $this->assertEmpty($channels);
    }

    public function test_notification_via_skips_push_when_no_tokens(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456']);
        $cart = $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);

        $notification = new AbandonedCartNotification($cart);
        $channels = $notification->via($customer);

        $this->assertNotContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains('mail', $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_notification_via_skips_whatsapp_when_no_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => null]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $cart = $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);

        $notification = new AbandonedCartNotification($cart);
        $channels = $notification->via($customer);

        $this->assertNotContains(WhatsAppChannel::class, $channels);
        $this->assertContains(ExpoNotificationsChannel::class, $channels);
    }

    public function test_notification_via_returns_mail_and_database_only_for_guest(): void
    {
        $cart = $this->createCart(['email' => 'guest@example.com']);

        $notification = new AbandonedCartNotification($cart);
        $notifiable = new \stdClass();

        $channels = $notification->via($notifiable);

        $this->assertEqualsCanonicalizing(['mail', 'database'], $channels);
    }

    public function test_can_be_disabled_per_channel(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456']);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $cart = $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);

        $notification = new AbandonedCartNotification($cart, 1, 'SAVE10', false, false, false);
        $channels = $notification->via($customer);

        $this->assertEquals(['database'], $channels);
    }

    public function test_to_mail_english_default(): void
    {
        $cart = $this->createCart([
            'cart_data' => [['name' => 'Phone Case', 'quantity' => 2, 'price' => 15.00]],
        ]);

        $notifiable = new \stdClass();
        $notifiable->email = 'test@example.com';

        $notification = new AbandonedCartNotification($cart, 1, 'SAVE10');
        $mail = $notification->toMail($notifiable);

        $this->assertStringContainsString('You left items in your cart', $mail->subject);
        $this->assertStringContainsString('Complete your purchase', $mail->greeting);
        $this->assertStringContainsString('Resume Checkout', $mail->actionText);
    }

    public function test_to_mail_second_reminder_has_coupon(): void
    {
        app()->setLocale('fr');
        $cart = $this->createCart();

        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new AbandonedCartNotification($cart, 2, 'WELCOME10');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('WELCOME10', (string) $mail->render());
    }

    public function test_to_mail_third_reminder_has_last_chance_messaging(): void
    {
        app()->setLocale('fr');
        $cart = $this->createCart();

        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new AbandonedCartNotification($cart, 3, 'SAVE10');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('Dernière chance', $mail->subject);
        $this->assertStringContainsString('SAVE10', (string) $mail->render());
    }

    public function test_to_expo_notification_returns_correct_message(): void
    {
        $cart = $this->createCart();

        $customer = Customer::factory()->create();
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new AbandonedCartNotification($cart, 2, 'SAVE10');
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Still thinking', $message->title);
        $this->assertStringContainsString('SAVE10', $message->body);
        $this->assertEquals('marketing', $message->channelId);
        $this->assertStringContainsString('Cart', $message->jsonData);
    }

    public function test_to_expo_notification_french(): void
    {
        $cart = $this->createCart();

        $customer = Customer::factory()->create(['locale' => 'fr']);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new AbandonedCartNotification($cart, 1, 'SAVE10');
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('articles dans votre panier', $message->title);
    }

    public function test_to_whatsapp_returns_correct_string(): void
    {
        $cart = $this->createCart();

        $customer = Customer::factory()->create();

        $notification = new AbandonedCartNotification($cart, 1);
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('left items', $text);
        $this->assertStringContainsString('/cart', $text);
    }

    public function test_to_whatsapp_french(): void
    {
        $cart = $this->createCart();

        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new AbandonedCartNotification($cart, 3, 'SOLDES20');
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('Dernière chance', $text);
        $this->assertStringContainsString('SOLDES20', $text);
    }

    public function test_to_array_structure(): void
    {
        $cart = $this->createCart();

        $notification = new AbandonedCartNotification($cart, 2, 'CODE10');
        $data = $notification->toArray(new \stdClass());

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertArrayHasKey('reminder_number', $data);
        $this->assertEquals(2, $data['reminder_number']);
        $this->assertStringContainsString('/cart', $data['action_url']);
    }

    public function test_send_abandoned_cart_reminders_job_sends_first_reminder(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $cart = $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'cart_data' => [['name' => 'Test', 'quantity' => 1, 'price' => 10]],
            'reminder_sent_at' => null,
        ]);

        $job = new SendAbandonedCartReminders();
        $job->handle();

        Notification::assertSentTo(
            $customer,
            AbandonedCartNotification::class,
            fn ($notification): bool => $notification->reminderNumber === 1
        );

        $this->assertNotNull($cart->fresh()->reminder_sent_at);
    }

    public function test_send_abandoned_cart_reminders_job_skips_recovered_carts(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'recovered_at' => now(),
        ]);

        $job = new SendAbandonedCartReminders();
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_send_abandoned_cart_reminders_job_reads_site_settings(): void
    {
        Notification::fake();

        SiteSetting::create([
            'abandoned_cart_config' => [
                'coupon_code' => 'TESTCODE',
                'enable_push' => false,
                'enable_whatsapp' => false,
                'enable_email' => true,
            ],
        ]);

        $customer = Customer::factory()->create(['marketing_opt_in' => true, 'phone' => '+22507123456']);
        $this->createCart([
            'customer_id' => $customer->id,
            'email' => $customer->email,
        ]);

        $job = new SendAbandonedCartReminders();
        $job->handle();

        Notification::assertSentTo(
            $customer,
            AbandonedCartNotification::class,
            fn ($notification): bool => $notification->couponCode === 'TESTCODE'
                && $notification->enablePush === false
                && $notification->enableWhatsApp === false
        );
    }

    public function test_send_abandoned_cart_reminders_job_filters_guests_without_email(): void
    {
        Notification::fake();

        $this->createCart(['email' => null]);

        $job = new SendAbandonedCartReminders();
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_send_abandoned_cart_reminders_job_handles_guest_carts(): void
    {
        Notification::fake();

        $cart = $this->createCart(['email' => 'guest@example.com', 'customer_id' => null]);

        $job = new SendAbandonedCartReminders();
        $job->handle();

        $this->assertNotNull($cart->fresh()->reminder_sent_at);
    }

    public function test_notification_french_via_customer_locale(): void
    {
        $cart = $this->createCart();

        $customer = Customer::factory()->create(['locale' => 'fr', 'preferred_language' => null]);

        $notification = new AbandonedCartNotification($cart, 1);
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('articles dans votre panier', $mail->subject);
        $this->assertStringContainsString('Finalisez', $mail->greeting);
    }

    public function test_notification_english_when_locale_is_en(): void
    {
        $cart = $this->createCart();

        $customer = Customer::factory()->create(['locale' => 'en']);

        $notification = new AbandonedCartNotification($cart, 1);
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('You left items', $mail->subject);
        $this->assertStringContainsString('Complete your purchase', $mail->greeting);
    }
}
