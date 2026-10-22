<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\SiteSetting;
use App\Notifications\Marketing\CrossSellNotification;
use App\Notifications\Marketing\WinBackNotification;
use App\Notifications\ReviewRequestNotification;
use App\Notifications\Orders\DeliveryConfirmedNotification;
use App\Notifications\Orders\OrderShippedNotification;
use App\Jobs\SendPostPurchaseCrossSells;
use App\Jobs\SendWinBackReminders;
use App\Jobs\RequestProductReviewJob;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\ProductRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use YieldStudio\LaravelExpoNotifier\ExpoNotificationsChannel;

class PostPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('en');

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('products', 'searchable_text')) {
            Schema::table('products', function ($table): void {
                $table->text('searchable_text')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'customer_status')) {
            Schema::table('orders', function ($table): void {
                $table->string('customer_status', 50)->nullable()->after('status');
            });
        }
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::withoutEvents(fn () => Product::create(array_merge([
            'name' => 'Test Product ' . uniqid(),
            'slug' => 'test-product-' . uniqid(),
            'code' => 'P' . strtoupper(Str::random(6)),
            'selling_price' => 5000,
            'cost_price' => 2000,
            'status' => 'active',
            'is_active' => true,
        ], $attrs)));
    }

    // ===================== CrossSellNotification Unit Tests =====================

    public function test_cross_sell_via_returns_all_channels(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456', 'marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect(), 'CODE10', true, true, true);
        $channels = $notification->via($customer);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_cross_sell_via_respects_marketing_opt_out(): void
    {
        $customer = Customer::factory()->create(['marketing_opt_in' => false]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect(), 'CODE10');
        $channels = $notification->via($customer);

        $this->assertEmpty($channels);
    }

    public function test_cross_sell_to_mail_contains_product_names(): void
    {
        $product1 = $this->makeProduct(['name' => 'Wireless Earbuds']);
        $product2 = $this->makeProduct(['name' => 'Phone Case']);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect([$product1, $product2]), 'WELCOME10');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('Wireless Earbuds', (string) $mail->render());
        $this->assertStringContainsString('Phone Case', (string) $mail->render());
        $this->assertStringContainsString('WELCOME10', (string) $mail->render());
    }

    public function test_cross_sell_to_mail_french(): void
    {
        $product = $this->makeProduct(['name' => 'Casque Bluetooth']);
        $customer = Customer::factory()->create(['locale' => 'fr']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect([$product]));
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('Merci pour votre commande', $mail->greeting);
        $this->assertStringContainsString('10% de réduction', (string) $mail->render());
    }

    public function test_cross_sell_to_expo_notification(): void
    {
        $product = $this->makeProduct(['name' => 'Power Bank']);
        $customer = Customer::factory()->create();
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect([$product]));
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Complete your order', $message->title);
        $this->assertStringContainsString('Power Bank', $message->body);
    }

    public function test_cross_sell_to_expo_notification_french(): void
    {
        $product = $this->makeProduct(['name' => 'Batterie externe']);
        $customer = Customer::factory()->create(['locale' => 'fr']);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect([$product]));
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Complétez', $message->title);
        $this->assertStringContainsString('Batterie', $message->body);
    }

    public function test_cross_sell_to_whatsapp(): void
    {
        $product = $this->makeProduct(['name' => 'USB Cable']);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'number' => 'ORD-001']);

        $notification = new CrossSellNotification($order, collect([$product]), 'EXTRA5');
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('ORD-001', $text);
        $this->assertStringContainsString('EXTRA5', $text);
        $this->assertStringContainsString('USB Cable', $text);
    }

    public function test_cross_sell_to_whatsapp_french(): void
    {
        $product = $this->makeProduct(['name' => 'Câble USB']);
        $customer = Customer::factory()->create(['locale' => 'fr']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $notification = new CrossSellNotification($order, collect([$product]), 'PROMO5');
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('Merci pour votre commande', $text);
        $this->assertStringContainsString('PROMO5', $text);
    }

    // ===================== WinBackNotification Unit Tests =====================

    public function test_win_back_via_returns_all_channels(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456', 'marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new WinBackNotification($customer, 'MISSYOU10', 60, true, true, true);
        $channels = $notification->via($customer);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_win_back_via_respects_marketing_opt_out(): void
    {
        $customer = Customer::factory()->create(['marketing_opt_in' => false]);

        $notification = new WinBackNotification($customer);
        $channels = $notification->via($customer);

        $this->assertEmpty($channels);
    }

    public function test_win_back_to_mail_includes_inactive_days(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Alice']);

        $notification = new WinBackNotification($customer, 'COMEBACK', 45);
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('45 days', $mail->introLines[0] ?? '');
        $this->assertStringContainsString('COMEBACK', (string) $mail->render());
    }

    public function test_win_back_to_mail_french(): void
    {
        App::setLocale('fr');
        $customer = Customer::factory()->create(['locale' => 'fr', 'first_name' => 'Alice']);

        $notification = new WinBackNotification($customer, 'REVIENS', 60);
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('60 jours', (string) $mail->render());
        $this->assertStringContainsString('REVIENS', (string) $mail->render());
    }

    public function test_win_back_to_expo_notification(): void
    {
        $customer = Customer::factory()->create();
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new WinBackNotification($customer, 'MISSYOU10');
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('We miss you', $message->title);
        $this->assertStringContainsString('MISSYOU10', $message->body);
    }

    public function test_win_back_to_whatsapp_french(): void
    {
        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new WinBackNotification($customer, 'REVIENS10', 60);
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('Vous nous manquez', $text);
        $this->assertStringContainsString('REVIENS10', $text);
        $this->assertStringContainsString('60 jours', $text);
    }

    // ===================== ReviewRequestNotification Unit Tests =====================

    public function test_review_request_via_includes_push_and_whatsapp(): void
    {
        $customer = Customer::factory()->create(['phone' => '+22507123456', 'marketing_opt_in' => true]);
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);
        $orderItem = OrderItem::factory()->create();

        $notification = new ReviewRequestNotification($orderItem);
        $channels = $notification->via($customer);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_review_request_to_expo_notification(): void
    {
        $product = $this->makeProduct(['name' => 'Bluetooth Speaker']);
        $variant = ProductVariant::factory()->for($product)->create();
        $orderItem = OrderItem::factory()->create(['product_variant_id' => $variant->id]);
        $customer = Customer::factory()->create();
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new ReviewRequestNotification($orderItem);
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Write a review', $message->title);
        $this->assertStringContainsString('Bluetooth Speaker', $message->body);
    }

    public function test_review_request_to_whatsapp(): void
    {
        $product = $this->makeProduct(['name' => 'Phone Stand']);
        $order = Order::factory()->create(['number' => 'ORD-123']);
        $variant = ProductVariant::factory()->for($product)->create();
        $orderItem = OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);
        $customer = Customer::factory()->create();

        $notification = new ReviewRequestNotification($orderItem);
        $text = $notification->toWhatsApp($customer);

        $this->assertStringContainsString('ORD-123', $text);
        $this->assertStringContainsString('Phone Stand', $text);
    }

    public function test_review_request_french(): void
    {
        App::setLocale('fr');
        $product = $this->makeProduct(['name' => 'Écouteurs']);
        $order = Order::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $orderItem = OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);
        $customer = Customer::factory()->create(['locale' => 'fr']);

        $notification = new ReviewRequestNotification($orderItem);
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString("Comment s'est passé", $mail->subject);
    }

    // ===================== DeliveryConfirmedNotification Unit Tests =====================

    public function test_delivery_confirmed_via_includes_push(): void
    {
        $order = Order::factory()->create();
        $customer = $order->customer;
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new DeliveryConfirmedNotification($order);
        $channels = $notification->via($customer);

        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_delivery_confirmed_to_expo_notification(): void
    {
        $order = Order::factory()->create(['number' => 'ORD-456']);
        $customer = $order->customer;
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new DeliveryConfirmedNotification($order);
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Order delivered', $message->title);
        $this->assertStringContainsString('ORD-456', $message->body);
        $this->assertEquals('orders', $message->channelId);
    }

    public function test_delivery_confirmed_french(): void
    {
        $order = Order::factory()->create();
        $customer = $order->customer;
        $customer->update(['locale' => 'fr']);

        $notification = new DeliveryConfirmedNotification($order);
        $notification = $notification->locale('fr');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('Livré', $mail->subject);
        $this->assertStringContainsString('Bonjour', $mail->greeting);
    }

    // ===================== OrderShippedNotification Unit Tests =====================

    public function test_order_shipped_via_includes_push(): void
    {
        $order = Order::factory()->create();
        $customer = $order->customer;
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new OrderShippedNotification($order);
        $channels = $notification->via($customer);

        $this->assertContains(ExpoNotificationsChannel::class, $channels);
        $this->assertContains(WhatsAppChannel::class, $channels);
    }

    public function test_order_shipped_to_expo_notification(): void
    {
        $order = Order::factory()->create(['number' => 'ORD-789']);
        $customer = $order->customer;
        $customer->expoTokens()->create(['value' => 'ExponentPushToken-xxxxxxxxxxxx']);

        $notification = new OrderShippedNotification($order, 'TRACK123', 'DHL');
        $message = $notification->toExpoNotification($customer);

        $this->assertStringContainsString('Order shipped', $message->title);
        $this->assertStringContainsString('ORD-789', $message->body);
        $this->assertEquals('orders', $message->channelId);
    }

    public function test_order_shipped_french(): void
    {
        $order = Order::factory()->create();
        $customer = $order->customer;
        $customer->update(['locale' => 'fr']);

        $notification = new OrderShippedNotification($order, null, 'DHL');
        $notification = $notification->locale('fr');
        $mail = $notification->toMail($customer);

        $this->assertStringContainsString('expédiée', $mail->subject);
        $this->assertStringContainsString('Bonjour', $mail->greeting);
    }

    // ===================== SendPostPurchaseCrossSells Job Tests =====================

    public function test_cross_sell_job_sends_notification_for_delivered_orders(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $product = $this->makeProduct();
        $recProduct = $this->makeProduct(); // must have is_active=true for relatedProducts to find it

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_status' => 'delivered',
            'cross_sell_sent_at' => null,
        ]);

        $variant = ProductVariant::factory()->for($product)->create();
        $orderItem = OrderItem::factory()
            ->for($order)
            ->create([
                'fulfillment_status' => 'fulfilled',
                'product_variant_id' => $variant->id,
            ]);

        Shipment::factory()
            ->for($orderItem)
            ->create(['delivered_at' => now()->subDays(10)]);

        $allProducts = Product::all();
        $job = new SendPostPurchaseCrossSells();
        $job->handle();

        Notification::assertSentTo($customer, CrossSellNotification::class);
    }

    public function test_cross_sell_job_skips_orders_already_sent(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_status' => 'delivered',
            'cross_sell_sent_at' => now(),
        ]);

        OrderItem::factory()->for($order)->create();

        $job = new SendPostPurchaseCrossSells();
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_cross_sell_job_respects_site_settings(): void
    {
        Notification::fake();

        SiteSetting::create([
            'cross_sell_config' => [
                'delay_days' => 1,
                'coupon_code' => 'CUSTOMCODE',
                'enable_push' => false,
                'enable_whatsapp' => false,
                'enable_email' => true,
            ],
        ]);

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        $product = $this->makeProduct();
        $recProduct = $this->makeProduct(); // must have is_active=true for relatedProducts
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_status' => 'delivered',
        ]);
        $variant = ProductVariant::factory()->for($product)->create();
        $orderItem = OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);
        Shipment::factory()->for($orderItem)->create(['delivered_at' => now()->subDays(2)]);

        $job = new SendPostPurchaseCrossSells();
        $job->handle();

        Notification::assertSentTo(
            $customer,
            CrossSellNotification::class,
            fn ($notification): bool => $notification->couponCode === 'CUSTOMCODE'
                && $notification->enablePush === false
                && $notification->enableWhatsApp === false
        );
    }

    // ===================== SendWinBackReminders Job Tests =====================

    public function test_win_back_job_sends_for_inactive_customers(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(90),
        ]);

        $job = new SendWinBackReminders();
        $job->handle();

        Notification::assertSentTo($customer, WinBackNotification::class);
    }

    public function test_win_back_job_skips_recently_active_customers(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(10),
        ]);

        $job = new SendWinBackReminders();
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_win_back_job_respects_marketing_opt_out(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create(['marketing_opt_in' => false]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(90),
        ]);

        $job = new SendWinBackReminders();
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_win_back_job_respects_site_settings(): void
    {
        Notification::fake();

        SiteSetting::create([
            'win_back_config' => [
                'inactivity_days' => 30,
                'coupon_code' => 'CUSTOMWB',
                'enable_push' => false,
                'enable_whatsapp' => false,
                'enable_email' => true,
            ],
        ]);

        $customer = Customer::factory()->create(['marketing_opt_in' => true]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'placed_at' => now()->subDays(40),
        ]);

        $job = new SendWinBackReminders();
        $job->handle();

        Notification::assertSentTo(
            $customer,
            WinBackNotification::class,
            fn ($notification): bool => $notification->couponCode === 'CUSTOMWB'
        );
    }

    public function test_win_back_job_skips_customers_without_orders(): void
    {
        Notification::fake();

        Customer::factory()->create(['marketing_opt_in' => true]);

        $job = new SendWinBackReminders();
        $job->handle();

        Notification::assertNothingSent();
    }
}
