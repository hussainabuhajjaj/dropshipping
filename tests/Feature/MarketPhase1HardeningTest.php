<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Common\Models\Address;
use App\Domain\Fulfillment\Services\CJFreightService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Http\Controllers\Api\Mobile\V1\CheckoutController as MobileCheckoutController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Services\Api\ApiResponse;
use App\Services\Pricing\MarketPriceContext;
use App\Services\Pricing\MarketPriceResolver;
use App\Support\StorefrontMarket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase 1 hardening: CI immutability, UAE runtime pricing, mobile parity, and
 * authoritative destination resolution for checkout/order creation.
 *
 * No live CJ calls are made; the CJ client is mocked.
 */
class MarketPhase1HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function persistProduct(array $overrides = []): Product
    {
        $meta = array_merge([
            'weight_kg' => 1.0,
            'cj_shipping' => 2.0,
            'external_shipping' => 3.0,
            'margin_used' => 0.45,
            'category_multiplier' => 1.2,
        ], $overrides['pricing_meta'] ?? []);

        unset($overrides['pricing_meta']);

        return Product::query()->create(array_merge([
            'slug' => 'market-test-'.Str::random(8),
            'name' => 'Market Test Product',
            'status' => 'active',
            'currency' => 'USD',
            'cost_price' => 10.0,
            'selling_price' => 26.10,
        ], $overrides, ['pricing_meta' => $meta]));
    }

    private function persistVariant(Product $product, array $overrides = []): ProductVariant
    {
        return ProductVariant::query()->create(array_merge([
            'product_id' => $product->id,
            'title' => 'Default',
            'sku' => 'SKU-'.Str::random(8),
            'price' => 31.50,
            'currency' => 'USD',
            'weight_grams' => 1000,
            'metadata' => ['cj_vid' => 'VID-TEST-1'],
        ], $overrides));
    }

    private function persistedCart(Product $product, ProductVariant $variant, int $quantity = 2): array
    {
        $cart = Cart::query()->create(['session_id' => 'sess-'.Str::random(8)]);
        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'fulfillment_provider_id' => 1,
            'quantity' => $quantity,
        ]);
        $cart->load('items');

        return [$cart, $item];
    }

    public function test_ci_cart_subtotal_and_unit_price_match_stored_values(): void
    {
        $product = $this->persistProduct();
        $variant = $this->persistVariant($product);
        [$cart, $item] = $this->persistedCart($product, $variant, 2);

        app(MarketPriceContext::class)->setCountry('CI');

        $this->assertSame(31.50, $item->getSinglePrice());
        $this->assertSame(63.0, $cart->subTotal());
        $this->assertSame(26.10, (float) $product->refresh()->selling_price);
    }

    public function test_ci_cart_subtotal_stays_stored_even_when_session_is_uae(): void
    {
        $product = $this->persistProduct();
        $variant = $this->persistVariant($product);
        [$cart, $item] = $this->persistedCart($product, $variant, 2);

        // Simulate checkout pinning the authoritative destination to CI while
        // the storefront session still says UAE.
        session()->put(StorefrontMarket::SESSION_KEY, 'AE');
        app(MarketPriceContext::class)->setCountry('CI');

        $this->assertSame(31.50, $item->getSinglePrice());
        $this->assertSame(63.0, $cart->subTotal());
    }

    public function test_ci_variant_and_product_prices_resolve_to_stored_values(): void
    {
        $product = $this->persistProduct();
        $variant = $this->persistVariant($product);

        $resolver = app(MarketPriceResolver::class);

        $this->assertSame(31.50, $resolver->priceForVariant($variant, $product, 'CI'));
        $this->assertSame(26.10, $resolver->priceForProduct($product, 'CI'));
    }

    public function test_uae_cart_unit_price_is_runtime_price_not_stored_price(): void
    {
        $product = $this->persistProduct();
        $variant = $this->persistVariant($product);
        [$cart, $item] = $this->persistedCart($product, $variant, 2);

        app(MarketPriceContext::class)->setCountry('AE');

        // (10 cost + 2 cj inbound) * 1.45 = 17.40; no external shipping.
        $this->assertSame(17.40, $item->getSinglePrice());
        $this->assertSame(34.80, $cart->subTotal());
        $this->assertNotSame(26.10, $item->getSinglePrice());
    }

    public function test_uae_pricing_never_writes_product_columns(): void
    {
        $product = $this->persistProduct();
        $this->persistVariant($product);

        $beforeSellingPrice = (float) $product->selling_price;
        $beforeMeta = $product->pricing_meta;
        $beforeUpdatedAt = $product->updated_at?->toDateTimeString();

        app(MarketPriceResolver::class)->priceForProduct($product, 'AE');

        $product->refresh();

        $this->assertSame($beforeSellingPrice, (float) $product->selling_price);
        $this->assertSame($beforeMeta, $product->pricing_meta);
        $this->assertSame($beforeUpdatedAt, $product->updated_at?->toDateTimeString());
    }

    public function test_uae_strict_cart_price_fails_when_cost_is_missing(): void
    {
        $product = $this->persistProduct(['cost_price' => null]);
        $variant = $this->persistVariant($product);
        [, $item] = $this->persistedCart($product, $variant);

        app(MarketPriceContext::class)->setCountry('AE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid cost_price/');

        $item->getSinglePrice();
    }

    public function test_uae_strict_cart_price_fails_when_weight_is_missing(): void
    {
        $product = $this->persistProduct(['pricing_meta' => ['weight_kg' => 0]]);
        $variant = $this->persistVariant($product, ['weight_grams' => null]);
        [, $item] = $this->persistedCart($product, $variant);

        app(MarketPriceContext::class)->setCountry('AE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no resolvable weight/');

        $item->getSinglePrice();
    }

    public function test_uae_shipping_quote_uses_cj_cn_to_ae_with_destination_zip(): void
    {
        $captured = [];
        $client = $this->createMock(CJDropshippingClient::class);
        $client->method('freightCalculate')->willReturnCallback(function (array $payload) use (&$captured) {
            $captured[] = $payload;

            return ApiResponse::success([[
                'logisticName' => 'CJE',
                'freight' => 12.34,
                'currency' => 'USD',
                'dayMin' => 7,
                'dayMax' => 12,
            ]]);
        });

        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->bind(CJFreightService::class, fn () => new CJFreightService($client));

        $product = $this->persistProduct();
        $variant = $this->persistVariant($product);
        [$cart] = $this->persistedCart($product, $variant);

        $method = new ReflectionMethod($cart, 'cjUaeFreightQuote');
        $method->setAccessible(true);
        $result = $method->invoke($cart, $cart->items, [
            'country' => 'AE',
            'province' => 'Dubai',
            'city' => 'Dubai',
            'zip' => '00000',
        ], false);

        $this->assertFalse($result['unavailable']);
        $this->assertSame(12.34, $result['line']['logistic_price']);
        $this->assertSame('CJE', $result['line']['logistic_name']);
        $this->assertNotEmpty($captured);

        foreach ($captured as $payload) {
            $inner = $payload['reqDTOS'][0] ?? $payload;
            $this->assertSame('CN', $inner['startCountryCode']);
            $this->assertSame('AE', $inner['endCountryCode']);
            $this->assertSame('00000', $inner['zip']);
        }
    }

    public function test_mobile_checkout_destination_uses_validated_country_and_defaults_to_ci(): void
    {
        $controller = app(MobileCheckoutController::class);
        $method = new ReflectionMethod($controller, 'resolveCheckoutDestination');
        $method->setAccessible(true);

        $uae = $method->invoke($controller, [
            'country' => 'ae',
            'state' => 'Dubai',
            'city' => 'Dubai',
            'postal_code' => '00000',
        ]);

        $this->assertSame('AE', $uae['country']);
        $this->assertSame('Dubai', $uae['province']);
        $this->assertSame('Dubai', $uae['city']);
        $this->assertSame('00000', $uae['zip']);

        $default = $method->invoke($controller, []);

        $this->assertSame('CI', $default['country']);
        $this->assertArrayNotHasKey('province', $default);
        $this->assertArrayNotHasKey('city', $default);
        $this->assertArrayNotHasKey('zip', $default);
    }

    public function test_mobile_product_endpoints_resolve_uae_from_country_query(): void
    {
        $request = Request::create('/api/mobile/v1/products?country=AE', 'GET');
        $this->app->instance('request', $request);

        $context = new MarketPriceContext(app(MarketPriceResolver::class));

        $this->assertSame('AE', $context->country());
        $this->assertTrue($context->isUae());
    }

    public function test_mobile_product_resource_returns_uae_runtime_price(): void
    {
        $product = $this->persistProduct();
        $this->persistVariant($product);

        $request = Request::create('/api/mobile/v1/products?country=AE', 'GET');
        $this->app->instance('request', $request);

        $payload = \App\Http\Resources\Mobile\V1\ProductResource::make($product->refresh())
            ->resolve($request);

        // (10 + 2) * 1.45 = 17.40 (no category multiplier, no external shipping).
        $this->assertSame(17.40, (float) $payload['price']);
    }

    public function test_payment_destination_ignores_stale_uae_session_when_no_country_is_submitted(): void
    {
        session()->put(StorefrontMarket::SESSION_KEY, 'AE');

        $controller = app(PaymentController::class);
        $method = new ReflectionMethod($controller, 'resolvePaymentDestinationCountry');
        $method->setAccessible(true);

        $request = Request::create('/payment/checkout', 'POST', ['method' => 'card']);

        $this->assertSame('CI', $method->invoke($controller, $request));
    }

    public function test_payment_destination_prefers_saved_address_over_submitted_country(): void
    {
        $customer = Customer::query()->create([
            'first_name' => 'Test',
            'email' => 'market-'.Str::random(6).'@example.com',
            'address_line1' => 'Test street',
        ]);

        $address = Address::query()->create([
            'customer_id' => $customer->id,
            'name' => 'Test Customer',
            'phone' => '000000000',
            'line1' => 'Test street',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'postal_code' => '00000',
            'country' => 'AE',
            'type' => 'shipping',
        ]);

        $this->actingAs($customer, 'customer');

        $controller = app(PaymentController::class);
        $method = new ReflectionMethod($controller, 'resolvePaymentDestinationCountry');
        $method->setAccessible(true);

        $request = Request::create('/payment/checkout', 'POST', [
            'method' => 'card',
            'address_id' => $address->id,
            'country' => 'CI',
        ]);

        // The order address will be the saved AE address, so the market must
        // follow it and never the (possibly stale) submitted country.
        $this->assertSame('AE', $method->invoke($controller, $request));
    }
}
