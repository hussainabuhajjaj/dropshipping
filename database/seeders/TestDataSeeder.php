<?php

namespace Database\Seeders;

use App\Domain\Common\Models\Address;
use App\Domain\Fulfillment\Models\FulfillmentAttempt;
use App\Domain\Fulfillment\Models\FulfillmentEvent;
use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Models\SupplierMetric;
use App\Domain\Orders\Models\OrderAuditLog;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Orders\Models\TrackingEvent;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Models\PaymentWebhook;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Domain\Products\Models\ProductImage;
use App\Domain\Products\Models\ProductVariant;
use App\Domain\Products\Models\SupplierProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'test@example.com')->first();

        $customer = Customer::updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'first_name' => 'Amina',
                'last_name' => 'Diallo',
                'phone' => '+22501020304',
                'country_code' => 'CI',
                'city' => 'Abidjan',
                'region' => 'Abidjan',
                'address_line1' => 'Cocody Block 12',
                'address_line2' => 'Residence Simbazu',
                'postal_code' => '00225',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $provider = FulfillmentProvider::firstOrCreate(
            ['code' => 'manual'],
            [
                'name' => 'Manual Supplier',
                'type' => 'manual',
                'driver_class' => \App\Domain\Fulfillment\Strategies\ManualFulfillmentStrategy::class,
                'credentials' => [],
                'settings' => [],
                'contact_info' => ['channel' => 'email'],
                'notes' => 'Seeded provider.',
                'is_active' => true,
                'is_blacklisted' => false,
                'retry_limit' => 2,
                'contact_email' => 'supplier@Simbazu.test',
                'contact_phone' => '+22505050505',
                'website_url' => 'https://supplier.test',
            ]
        );

        $supplier = FulfillmentProvider::firstOrCreate(
            ['code' => 'Simbazu-supplier'],
            [
                'name' => 'Simbazu Supplier Network',
                'type' => 'supplier',
                'driver_class' => \App\Domain\Fulfillment\Strategies\ManualFulfillmentStrategy::class,
                'credentials' => [],
                'settings' => [],
                'contact_info' => ['channel' => 'whatsapp'],
                'notes' => 'Primary supplier for seeded products.',
                'is_active' => true,
                'is_blacklisted' => false,
                'retry_limit' => 3,
                'contact_email' => 'ops@Simbazu.test',
                'contact_phone' => '+22507070707',
                'website_url' => 'https://supplier.Simbazu.test',
            ]
        );

        SupplierMetric::firstOrCreate(
            ['fulfillment_provider_id' => $provider->id],
            [
                'fulfilled_count' => 8,
                'failed_count' => 1,
                'refunded_count' => 0,
                'average_lead_time_days' => 9.5,
                'calculated_at' => now(),
            ]
        );

        SiteSetting::updateOrCreate([], [
            'site_name' => 'Simbazu',
            'site_description' => 'Trending essentials with transparent delivery.',
            'meta_title' => 'Simbazu Store',
            'meta_description' => 'Shop trending essentials with reliable shipping.',
            'meta_keywords' => 'dropshipping, Simbazu, essentials, ecommerce',
            'logo_path' => null,
            'favicon_path' => null,
            'timezone' => config('app.timezone', 'UTC'),
            'primary_color' => '#111827',
            'secondary_color' => '#2563eb',
            'accent_color' => '#f59e0b',
            'support_email' => 'support@Simbazu.test',
            'support_whatsapp' => '+22500000000',
            'support_phone' => '+22500000000',
            'delivery_window' => '7–18 business days',
            'shipping_message' => 'Standard tracked delivery to Côte d’Ivoire.',
            'customs_message' => 'Duties and VAT are disclosed before payment when available.',
            'default_fulfillment_provider_id' => $provider->id,
        ]);

        $product = Product::query()->first();
        if (! $product) {
            $category = Category::firstOrCreate(['name' => 'Electronics']);
            $product = Product::create([
                'slug' => 'Simbazu-smart-hub',
                'name' => 'Simbazu Smart Hub',
                'category_id' => $category->id,
                'description' => 'Smart home hub for everyday automation.',
                'selling_price' => 149.99,
                'cost_price' => 95.0,
                'status' => 'active',
                'currency' => 'USD',
                'default_fulfillment_provider_id' => $provider->id,
                'supplier_id' => $supplier->id,
                'supplier_product_url' => 'https://supplier.Simbazu.test/products/Simbazu-hub',
                'shipping_estimate_days' => 9,
                'is_active' => true,
                'is_featured' => true,
                'source_url' => null,
                'options' => ['Color'],
                'attributes' => ['origin' => 'CN'],
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'url' => 'https://picsum.photos/seed/Simbazu-hub/900/900',
                'position' => 1,
            ]);
        } else {
            $product->update([
                'default_fulfillment_provider_id' => $product->default_fulfillment_provider_id ?? $provider->id,
                'supplier_id' => $product->supplier_id ?? $supplier->id,
                'supplier_product_url' => $product->supplier_product_url ?? 'https://supplier.Simbazu.test/products/Simbazu-hub',
            ]);
        }

        $variant = ProductVariant::where('product_id', $product->id)->first();
        if (! $variant) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'AZ-HUB-STD',
                'title' => 'Standard',
                'price' => $product->selling_price ?? 149.99,
                'compare_at_price' => 199.99,
                'cost_price' => $product->cost_price ?? 95.0,
                'currency' => $product->currency ?? 'USD',
                'inventory_policy' => 'allow',
                'options' => ['Color' => 'Graphite'],
            ]);
        }

        SupplierProduct::firstOrCreate(
            [
                'product_variant_id' => $variant->id,
                'fulfillment_provider_id' => $supplier->id,
                'external_product_id' => 'EXT-AZ-HUB',
            ],
            [
                'external_sku' => 'AZ-HUB-EXT',
                'cost_price' => 90.0,
                'currency' => 'USD',
                'lead_time_days' => 7,
                'shipping_options' => ['standard' => 9],
                'is_active' => true,
            ]
        );

        $address = Address::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'line1' => 'Cocody Block 12',
            ],
            [
                'user_id' => null,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'line2' => 'Residence Simbazu',
                'city' => $customer->city,
                'state' => $customer->region,
                'postal_code' => $customer->postal_code,
                'country' => 'CI',
                'type' => 'shipping',
            ]
        );

        $order = Order::firstOrCreate(
            ['number' => 'AZ-0001'],
            [
                'user_id' => null,
                'customer_id' => $customer->id,
                'email' => $customer->email ?? 'customer@example.com',
                'status' => 'paid',
                'payment_status' => 'paid',
                'currency' => 'USD',
                'subtotal' => 149.99,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => 149.99,
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
                'shipping_method' => 'standard',
                'delivery_notes' => 'Leave at reception.',
                'placed_at' => now()->subDays(2),
            ]
        );

        $orderItem = OrderItem::query()
            ->where('order_id', $order->id)
            ->first();

        if (! $orderItem) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'fulfillment_provider_id' => $provider->id,
                'supplier_product_id' => null,
                'fulfillment_status' => 'fulfilled',
                'quantity' => 1,
                'unit_price' => $variant->price,
                'total' => $variant->price,
                'source_sku' => $variant->sku,
                'snapshot' => [
                    'name' => $product->name,
                    'variant' => $variant->title,
                ],
                'meta' => [
                    'media' => [$product->images()->first()?->url],
                ],
            ]);
        }

        $job = FulfillmentJob::firstOrCreate(
            ['order_item_id' => $orderItem->id],
            [
                'fulfillment_provider_id' => $provider->id,
                'payload' => ['order_number' => $order->number],
                'status' => 'succeeded',
                'external_reference' => 'FUL-AZ-0001',
                'dispatched_at' => now()->subDays(2),
                'fulfilled_at' => now()->subDay(),
            ]
        );

        FulfillmentAttempt::firstOrCreate(
            ['fulfillment_job_id' => $job->id, 'attempt_no' => 1],
            [
                'request_payload' => ['order' => $order->number],
                'response_payload' => ['status' => 'ok'],
                'status' => 'success',
            ]
        );

        FulfillmentEvent::firstOrCreate(
            ['order_item_id' => $orderItem->id, 'type' => 'dispatched'],
            [
                'fulfillment_provider_id' => $provider->id,
                'fulfillment_job_id' => $job->id,
                'status' => 'succeeded',
                'message' => 'Order sent to supplier.',
                'payload' => ['reference' => $job->external_reference],
            ]
        );

        $shipment = Shipment::firstOrCreate(
            ['order_item_id' => $orderItem->id],
            [
                'tracking_number' => 'TRK-AZ-0001',
                'carrier' => 'DHL',
                'tracking_url' => 'https://tracking.example.com/TRK-AZ-0001',
                'shipped_at' => now()->subDay(),
                'delivered_at' => now()->subHours(6),
                'raw_events' => [],
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment->id, 'external_id' => 'TRK-EVT-1'],
            [
                'status_code' => 'shipped',
                'status_label' => 'Shipped',
                'description' => 'Package departed origin facility.',
                'location' => 'Shenzhen',
                'occurred_at' => now()->subDay(),
                'payload' => ['carrier' => 'DHL'],
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment->id, 'external_id' => 'TRK-EVT-2'],
            [
                'status_code' => 'delivered',
                'status_label' => 'Delivered',
                'description' => 'Delivered to customer.',
                'location' => 'Abidjan',
                'occurred_at' => now()->subHours(6),
                'payload' => ['carrier' => 'DHL'],
            ]
        );

        $payment = Payment::firstOrCreate(
            [
                'order_id' => $order->id,
                'provider' => 'card',
                'provider_reference' => 'PAY-AZ-0001',
            ],
            [
                'status' => 'paid',
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'paid_at' => now()->subDay(),
                'meta' => ['method' => 'card'],
            ]
        );

        $refundedOrder = Order::firstOrCreate(
            ['number' => 'AZ-0002'],
            [
                'user_id' => null,
                'customer_id' => $customer->id,
                'email' => $customer->email ?? 'customer@example.com',
                'status' => 'refunded',
                'payment_status' => 'refunded',
                'currency' => 'USD',
                'subtotal' => 59.99,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 5.0,
                'grand_total' => 54.99,
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
                'shipping_method' => 'standard',
                'delivery_notes' => 'Refunded order scenario.',
                'placed_at' => now()->subDays(6),
            ]
        );

        $refundedItem = OrderItem::firstOrCreate(
            ['order_id' => $refundedOrder->id],
            [
                'product_variant_id' => $variant->id,
                'fulfillment_provider_id' => $supplier->id,
                'supplier_product_id' => null,
                'fulfillment_status' => 'cancelled',
                'quantity' => 1,
                'unit_price' => 59.99,
                'total' => 59.99,
                'source_sku' => $variant->sku,
                'snapshot' => [
                    'name' => $product->name,
                    'variant' => $variant->title,
                ],
                'meta' => [
                    'media' => [$product->images()->first()?->url],
                ],
            ]
        );

        $refundedPayment = Payment::firstOrCreate(
            [
                'order_id' => $refundedOrder->id,
                'provider' => 'mobile_money',
                'provider_reference' => 'PAY-AZ-REF-0002',
            ],
            [
                'status' => 'refunded',
                'amount' => $refundedOrder->grand_total,
                'currency' => $refundedOrder->currency,
                'paid_at' => now()->subDays(5),
                'meta' => ['method' => 'mobile_money'],
            ]
        );

        PaymentEvent::firstOrCreate(
            ['payment_id' => $refundedPayment->id, 'order_id' => $refundedOrder->id, 'type' => 'refunded'],
            [
                'status' => 'success',
                'message' => 'Refund issued.',
                'payload' => ['reference' => $refundedPayment->provider_reference],
            ]
        );

        OrderEvent::firstOrCreate(
            ['order_id' => $refundedOrder->id, 'type' => 'refunded'],
            [
                'status' => 'refunded',
                'message' => 'Order refunded.',
                'payload' => ['number' => $refundedOrder->number],
            ]
        );

        PaymentEvent::firstOrCreate(
            ['payment_id' => $payment->id, 'order_id' => $order->id, 'type' => 'paid'],
            [
                'status' => 'success',
                'message' => 'Payment captured.',
                'payload' => ['reference' => $payment->provider_reference],
            ]
        );

        PaymentWebhook::firstOrCreate(
            ['external_event_id' => 'evt_test_Simbazu_1'],
            [
                'payment_id' => $payment->id,
                'provider' => $payment->provider,
                'payload' => ['event' => 'payment.succeeded'],
                'processed_at' => now(),
            ]
        );

        OrderEvent::firstOrCreate(
            ['order_id' => $order->id, 'type' => 'placed'],
            [
                'status' => 'paid',
                'message' => 'Order placed and paid.',
                'payload' => ['number' => $order->number],
            ]
        );

        OrderAuditLog::firstOrCreate(
            ['order_id' => $order->id, 'action' => 'seeded'],
            [
                'user_id' => $admin?->id,
                'note' => 'Seeded test order.',
                'payload' => ['source' => 'seeder'],
            ]
        );

        $failedOrder = Order::firstOrCreate(
            ['number' => 'AZ-0003'],
            [
                'user_id' => null,
                'customer_id' => $customer->id,
                'email' => $customer->email ?? 'customer@example.com',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => 'USD',
                'subtotal' => 89.99,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => 89.99,
                'shipping_address_id' => $address->id,
                'billing_address_id' => $address->id,
                'shipping_method' => 'standard',
                'delivery_notes' => 'Failed payment scenario.',
                'placed_at' => now()->subDays(4),
            ]
        );

        $failedItem = OrderItem::firstOrCreate(
            ['order_id' => $failedOrder->id],
            [
                'product_variant_id' => $variant->id,
                'fulfillment_provider_id' => $provider->id,
                'supplier_product_id' => null,
                'fulfillment_status' => 'failed',
                'quantity' => 1,
                'unit_price' => 89.99,
                'total' => 89.99,
                'source_sku' => $variant->sku,
                'snapshot' => [
                    'name' => $product->name,
                    'variant' => $variant->title,
                ],
                'meta' => [
                    'media' => [$product->images()->first()?->url],
                ],
            ]
        );

        $failedPayment = Payment::firstOrCreate(
            [
                'order_id' => $failedOrder->id,
                'provider' => 'card',
                'provider_reference' => 'PAY-AZ-FAILED-0003',
            ],
            [
                'status' => 'failed',
                'amount' => $failedOrder->grand_total,
                'currency' => $failedOrder->currency,
                'paid_at' => null,
                'meta' => ['method' => 'card'],
            ]
        );

        PaymentEvent::firstOrCreate(
            ['payment_id' => $failedPayment->id, 'order_id' => $failedOrder->id, 'type' => 'failed'],
            [
                'status' => 'failed',
                'message' => 'Payment failed.',
                'payload' => ['reference' => $failedPayment->provider_reference],
            ]
        );

        $failedJob = FulfillmentJob::firstOrCreate(
            ['order_item_id' => $failedItem->id],
            [
                'fulfillment_provider_id' => $provider->id,
                'payload' => ['order_number' => $failedOrder->number],
                'status' => 'failed',
                'external_reference' => 'FUL-AZ-0003',
                'dispatched_at' => now()->subDays(3),
                'fulfilled_at' => null,
                'last_error' => 'Supplier timeout',
            ]
        );

        FulfillmentAttempt::firstOrCreate(
            ['fulfillment_job_id' => $failedJob->id, 'attempt_no' => 1],
            [
                'request_payload' => ['order' => $failedOrder->number],
                'response_payload' => ['status' => 'timeout'],
                'status' => 'failed',
                'error_message' => 'Timeout on supplier API.',
            ]
        );

        FulfillmentEvent::firstOrCreate(
            ['order_item_id' => $failedItem->id, 'type' => 'failed'],
            [
                'fulfillment_provider_id' => $provider->id,
                'fulfillment_job_id' => $failedJob->id,
                'status' => 'failed',
                'message' => 'Supplier failed to fulfill.',
                'payload' => ['reference' => $failedJob->external_reference],
            ]
        );

        $notificationPayload = [
            'title' => 'Welcome to Simbazu',
            'body' => 'Your account is ready.',
        ];

        $hasNotification = DB::table('notifications')
            ->where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customer->id)
            ->exists();

        if (! $hasNotification) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\Orders\\OrderConfirmedNotification',
                'notifiable_type' => Customer::class,
                'notifiable_id' => $customer->id,
                'data' => json_encode($notificationPayload, JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        PaymentMethod::firstOrCreate(
            ['customer_id' => $customer->id, 'provider' => 'card', 'last4' => '4242'],
            [
                'brand' => 'Visa',
                'exp_month' => 12,
                'exp_year' => now()->addYear()->year,
                'nickname' => 'Primary card',
                'is_default' => true,
            ]
        );

        GiftCard::firstOrCreate(
            ['code' => 'Simbazu-GIFT-100'],
            [
                'customer_id' => $customer->id,
                'balance' => 100,
                'currency' => 'USD',
                'status' => 'active',
                'expires_at' => now()->addYear(),
            ]
        );

        GiftCard::firstOrCreate(
            ['code' => 'Simbazu-GIFT-EXPIRED'],
            [
                'customer_id' => $customer->id,
                'balance' => 0,
                'currency' => 'USD',
                'status' => 'expired',
                'expires_at' => now()->subDays(10),
            ]
        );

        $coupon = Coupon::firstOrCreate(
            ['code' => 'Simbazu-15'],
            [
                'description' => '15% off your next order',
                'type' => 'percent',
                'amount' => 15,
                'is_active' => true,
            ]
        );

        CouponRedemption::firstOrCreate(
            ['coupon_id' => $coupon->id, 'customer_id' => $customer->id],
            ['status' => 'saved']
        );

        $redeemedCoupon = Coupon::firstOrCreate(
            ['code' => 'Simbazu-REDEEMED'],
            [
                'description' => 'Redeemed coupon sample',
                'type' => 'fixed',
                'amount' => 12,
                'is_active' => true,
                'starts_at' => now()->subDays(20),
                'ends_at' => now()->addDays(5),
            ]
        );

        CouponRedemption::firstOrCreate(
            ['coupon_id' => $redeemedCoupon->id, 'customer_id' => $customer->id],
            [
                'status' => 'redeemed',
                'redeemed_at' => now()->subDays(2),
            ]
        );

        Coupon::firstOrCreate(
            ['code' => 'Simbazu-INACTIVE'],
            [
                'description' => 'Inactive/expired coupon',
                'type' => 'percent',
                'amount' => 5,
                'is_active' => false,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->subDays(1),
            ]
        );

        \App\Models\ProductReview::firstOrCreate(
            ['order_item_id' => $orderItem->id],
            [
                'product_id' => $product->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'rating' => 5,
                'title' => 'Arrived fast',
                'body' => 'Packaging was great and delivery was quicker than expected.',
                'status' => 'approved',
            ]
        );
    }
}
