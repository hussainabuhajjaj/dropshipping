<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Services\CJFreightService;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductVariant;
use App\Models\Cart;
use App\Models\CartShipping;
use App\Services\Api\ApiResponse;
use App\Services\Pricing\MarketPriceContext;
use App\Services\Pricing\MarketPriceResolver;
use App\Support\StorefrontMarket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — UAE market-aware pricing & shipping (unit coverage).
 *
 * These tests exercise the resolver directly and the destination-aware cart
 * freight path with a mocked CJ client. They never touch live CJ endpoints
 * and never write to products / pricing_meta.
 */
class MarketPricingUaeTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Build an unsaved Product hydrated with realistic CI pricing inputs:
     * cost 10 + cj 2 + ext 3 = landed 15, margin .45 (1kg), mult 1.2.
     * CI stored selling_price = round(round(15*1.45,2)*1.2,2) = 26.10.
     */
    private function product(array $overrides = []): Product
    {
        $meta = array_merge([
            'weight_kg' => 1.0,
            'cj_shipping' => 2.0,
            'external_shipping' => 3.0,
            'margin_used' => 0.45,
            'category_multiplier' => 1.2,
        ], $overrides['pricing_meta'] ?? []);

        return new Product(array_merge([
            'id' => 101,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'active',
            'currency' => 'USD',
            'cost_price' => 10.0,
            'selling_price' => 26.10,
            'category_id' => 1,
            'supplier_type' => 'cj',
        ], $overrides, ['pricing_meta' => $meta]));
    }

    private function resolver(): MarketPriceResolver
    {
        return app(MarketPriceResolver::class);
    }

    public function test_ci_market_returns_stored_selling_price_unchanged(): void
    {
        $product = $this->product();

        $resolved = $this->resolver()->resolve($product, null, 'CI');

        $this->assertSame('ci', $resolved['market']);
        $this->assertSame(26.10, $resolved['price']);
        $this->assertSame('USD', $resolved['currency']);
        // Stored attribute itself untouched.
        $this->assertSame(26.10, (float) $product->selling_price);
    }

    public function test_ci_market_returns_stored_variant_price_unchanged(): void
    {
        $product = $this->product();
        $variant = new ProductVariant(['id' => 9, 'price' => 31.50, 'currency' => 'USD']);

        $resolved = $this->resolver()->resolve($product, $variant, 'CI');

        $this->assertSame('ci', $resolved['market']);
        $this->assertSame(31.50, $resolved['price']);
    }

    public function test_uae_price_excludes_ci_external_shipping(): void
    {
        $product = $this->product();

        $uae = $this->resolver()->priceForProduct($product, 'AE');

        // (10 cost + 2 cj inbound) * 1.45 * 1.2 = 20.88 — no external 3.0.
        $this->assertSame(20.88, $uae);
        // And it must differ from the CI stored price which embeds ext shipping.
        $this->assertNotSame((float) $product->selling_price, $uae);
    }

    public function test_unknown_country_keeps_existing_single_market_behavior(): void
    {
        $product = $this->product();

        $resolved = $this->resolver()->resolve($product, null, 'FR');

        // Unknown countries keep the legacy stored-price behavior byte-for-byte.
        $this->assertSame('ci', $resolved['market']);
        $this->assertSame(26.10, $resolved['price']);
    }

    public function test_uae_price_fails_explicitly_when_cost_missing(): void
    {
        $product = $this->product(['cost_price' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid cost_price/');

        $this->resolver()->priceForProduct($product, 'AE');
    }

    public function test_uae_price_fails_explicitly_when_weight_missing(): void
    {
        $product = $this->product(['pricing_meta' => ['weight_kg' => 0]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no resolvable weight/');

        $this->resolver()->priceForProduct($product, 'AE');
    }

    public function test_listing_helper_degrades_gracefully_but_never_falls_back_to_ci_price(): void
    {
        $context = app(MarketPriceContext::class);
        $context->setCountry('AE');

        $bad = $this->product(['cost_price' => 0]);

        // Soft path logs + returns null...
        $this->assertNull($context->tryPriceForProduct($bad));

        // ...and the strict checkout path throws instead of silently using
        // the CI selling_price (which embeds CI external shipping).
        $this->expectException(\RuntimeException::class);
        $context->priceForProduct($bad);
    }

    public function test_switching_market_changes_displayed_price_without_touching_stored_price(): void
    {
        $product = $this->product();
        $storedBefore = (float) $product->selling_price;

        $context = app(MarketPriceContext::class);
        $context->setCountry('CI');
        $ciPrice = $context->priceForProduct($product);

        $context->setCountry('AE');
        $uaePrice = $context->priceForProduct($product);

        $this->assertSame($storedBefore, $ciPrice);
        $this->assertLessThan($ciPrice, $uaePrice);
        $this->assertSame($storedBefore, (float) $product->selling_price);
    }

    public function test_uae_checkout_freight_quotes_cn_to_ae_via_existing_service(): void
    {
        $capturedPayloads = [];

        $client = $this->createMock(CJDropshippingClient::class);
        $client->method('freightCalculate')
            ->willReturnCallback(function (array $payload) use (&$capturedPayloads) {
                $capturedPayloads[] = $payload;

                // Non-empty primary response: CJFreightService must NOT need
                // the tip-endpoint fallback, so exactly one call is made.
                return ApiResponse::success([
                    [
                        'logisticName' => 'CJE',
                        'freight' => 12.34,
                        'currency' => 'USD',
                        'dayMin' => 7,
                        'dayMax' => 12,
                    ],
                ]);
            });

        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->bind(CJFreightService::class, fn () => new CJFreightService($client));

        $cart = new Cart(['id' => 1, 'session_id' => 'test-session']);
        $cart->exists = true;

        $items = collect([
            new \App\Models\CartItem([
                'id' => 1,
                'cart_id' => 1,
                'product_id' => 101,
                'variant_id' => 9,
                'fulfillment_provider_id' => 1,
                'quantity' => 2,
            ]),
        ]);

        // cjUaeFreightQuote looks the variant up from the DB for its cj_vid.
        ProductVariant::query()->create([
            'id' => 9,
            'product_id' => 101,
            'sku' => 'TEST-VAR-9',
            'price' => 31.50,
            'metadata' => ['cj_vid' => 'VID-10'],
        ]);

        $reflection = new \ReflectionMethod($cart, 'cjUaeFreightQuote');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($cart, $items, [
            'country' => 'AE',
            'province' => 'Dubai',
            'city' => 'Dubai',
            'zip' => '00000',
        ], false);

        $this->assertFalse($result['unavailable']);
        $this->assertSame(12.34, $result['line']['logistic_price']);
        $this->assertSame('CJE', $result['line']['logistic_name']);

        // Exactly one freight call was made (empty primary response triggers
        // the tip-endpoint fallback in CJFreightService, so allow up to two,
        // but every payload must be CN -> AE with the customer's zip).
        $this->assertNotEmpty($capturedPayloads);
        foreach ($capturedPayloads as $payload) {
            $inner = $payload['reqDTOS'][0] ?? $payload;
            $this->assertSame('CN', $inner['startCountryCode']);
            $this->assertSame('AE', $inner['endCountryCode']);
            $this->assertSame('00000', $inner['zip']);
            $this->assertSame([['quantity' => 2, 'vid' => 'VID-10']], $inner['products']);
        }
    }

    public function test_uae_freight_quote_fails_closed_without_inventing_rates(): void
    {
        $client = $this->createMock(CJDropshippingClient::class);
        $client->method('freightCalculate')->willThrowException(new FulfillmentException('boom'));
        $this->app->instance(CJDropshippingClient::class, $client);
        $this->app->bind(CJFreightService::class, fn () => new CJFreightService($client));

        $cart = new Cart(['id' => 2, 'session_id' => 'test-session-2']);
        $cart->exists = true;

        $items = collect([
            new \App\Models\CartItem([
                'id' => 2, 'cart_id' => 2, 'product_id' => 101, 'variant_id' => 9,
                'fulfillment_provider_id' => 1, 'quantity' => 1,
            ]),
        ]);

        $reflection = new \ReflectionMethod($cart, 'cjUaeFreightQuote');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($cart, $items, ['country' => 'AE'], false);

        // No silent $14/kg or $15/kg fallback — shipping is marked unavailable.
        $this->assertTrue($result['unavailable']);
        $this->assertNull($result['line']);
        $this->assertSame(0, CartShipping::query()->where('cart_id', 2)->count());
    }

    public function test_storefront_market_helpers(): void
    {
        $this->assertSame('uae', StorefrontMarket::marketForCountry('AE'));
        $this->assertSame('ci', StorefrontMarket::marketForCountry('CI'));
        $this->assertSame('ci', StorefrontMarket::marketForCountry(null));
        $this->assertTrue(StorefrontMarket::isUaeCountry('ae'));
        $this->assertFalse(StorefrontMarket::isUaeCountry('CI'));

        session()->put(StorefrontMarket::SESSION_KEY, 'AE');
        $this->assertSame('AE', StorefrontMarket::resolveCountry(request()));
        $this->assertSame('uae', StorefrontMarket::currentMarket(request()));
    }
}
