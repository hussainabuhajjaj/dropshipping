<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Domain\Common\Models\Address;
use App\Domain\Products\Models\ProductVariant;
use App\Infrastructure\Payments\Clients\KorapayClient;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KorapayStorefrontRedirectUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_cart_checkout_sends_korapay_redirect_url_to_pay_cart_redirect(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        // Minimal product + variant + cart line
        $product = Product::factory()->create();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-' . uniqid(),
            'title' => 'Default',
            'price' => 10.00,
            'currency' => 'USD',
        ]);

        $cart = Cart::query()->create([
            'user_id' => $customer->id,
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'fulfillment_provider_id' => null,
            'quantity' => 1,
        ]);

        $address = Address::create([
            'customer_id' => $customer->id,
            'name' => 'Test Buyer',
            'phone' => '+22500000000',
            'line1' => '123 Test Street',
            'city' => 'Abidjan',
            'country' => 'CI',
            'type' => 'shipping',
        ]);

        $fake = new class extends KorapayClient {
            public array $lastPayload = [];
            public function __construct() {}
            public function initialize(array $payload): ApiResponse
            {
                $this->lastPayload = $payload;
                return ApiResponse::success([
                    'reference' => $payload['reference'] ?? 'ref_unknown',
                    'checkout_url' => 'https://pay.test/checkout',
                ]);
            }
        };
        $this->app->instance(KorapayClient::class, $fake);

        $response = $this->postJson('/pay/cart/checkout', [
            'method' => 'mobile_money',
            'address_id' => $address->id,
            'email' => $customer->email,
            'phone' => '+22500000000',
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'line1' => '123 Test Street',
            'line2' => null,
            'city' => 'Abidjan',
            'state' => null,
            'postal_code' => null,
            'country' => 'CI',
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $this->assertIsArray($fake->lastPayload);
        $this->assertStringContainsString('/pay/cart/redirect', (string) ($fake->lastPayload['redirect_url'] ?? ''));
    }
}
