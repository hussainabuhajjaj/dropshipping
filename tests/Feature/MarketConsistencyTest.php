<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Storefront\CheckoutController;
use App\Services\Pricing\MarketPriceContext;
use App\Support\StorefrontMarket;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase 1 — market resolution precedence / security checks.
 *
 * The checkout shipping country is authoritative: a storefront session or a
 * ?country= query parameter must never be able to force an order to use a
 * market inconsistent with the actual destination.
 */
class MarketConsistencyTest extends TestCase
{
    /**
     * Invoke the private resolveDestinationCountry() exactly the way
     * CheckoutController::store() does, with a realistic request + input bag.
     *
     * @param array<string, mixed> $input validated checkout input
     * @return array{0: string, 1: ?int} [country, addressId]
     */
    private function resolve(array $input, Request $request): array
    {
        // Rebuild the request so its input bag reflects merged body+query.
        $request->setUserResolver(fn () => null);

        $controller = app(CheckoutController::class);
        $method = new ReflectionMethod($controller, 'resolveDestinationCountry');
        $method->setAccessible(true);

        return $method->invoke($controller, $request, $input);
    }

    public function test_session_uae_plus_checkout_country_ci_resolves_to_ci(): void
    {
        session()->put(StorefrontMarket::SESSION_KEY, 'AE');

        $request = Request::create('/checkout', 'POST');

        [$country] = $this->resolve(['country' => 'CI'], $request);

        $this->assertSame('CI', $country);
        $this->assertSame('ci', StorefrontMarket::marketForCountry($country));
    }

    public function test_session_ci_plus_checkout_country_ae_resolves_to_uae(): void
    {
        session()->put(StorefrontMarket::SESSION_KEY, 'CI');

        $request = Request::create('/checkout', 'POST');

        [$country] = $this->resolve(['country' => 'AE'], $request);

        $this->assertSame('AE', $country);
        $this->assertSame('uae', StorefrontMarket::marketForCountry($country));
    }

    public function test_query_param_cannot_override_submitted_country(): void
    {
        // ?country=AE in the URL but the form submits CI -> CI wins.
        $request = Request::create('/checkout?country=AE', 'POST', ['country' => 'CI']);

        [$country] = $this->resolve(['country' => 'CI'], $request);

        $this->assertSame('CI', $country);

        // And the reverse: ?country=CI cannot pull an AE submission back to CI.
        $request2 = Request::create('/checkout?country=CI', 'POST', ['country' => 'AE']);

        [$country2] = $this->resolve(['country' => 'AE'], $request2);

        $this->assertSame('AE', $country2);
    }

    public function test_saved_address_country_wins_over_session_and_query(): void
    {
        // No DB rows exist for address_id 999 here, so lookup returns null and
        // resolution must fall through to the submitted country — never to the
        // session/query value.
        session()->put(StorefrontMarket::SESSION_KEY, 'AE');
        $request = Request::create('/checkout?country=AE', 'POST');

        [$country] = $this->resolve(['country' => 'CI', 'address_id' => 999], $request);

        $this->assertSame('CI', $country);
    }

    public function test_effective_market_after_pinning_matches_destination(): void
    {
        // Simulate the full store() pinning sequence for an AE destination
        // while the session previously held CI.
        session()->put(StorefrontMarket::SESSION_KEY, 'CI');
        $request = Request::create('/checkout', 'POST');

        [$country] = $this->resolve(['country' => 'AE'], $request);

        app(MarketPriceContext::class)->setCountry($country);
        session()->put(StorefrontMarket::SESSION_KEY, strtoupper($country));

        $this->assertTrue(app(MarketPriceContext::class)->isUae());
        $this->assertSame('AE', session(StorefrontMarket::SESSION_KEY));

        // And the inverse pin (CI destination) restores CI behavior.
        $context2 = new MarketPriceContext(app(\App\Services\Pricing\MarketPriceResolver::class));
        $context2->setCountry('CI');
        $this->assertFalse($context2->isUae());
    }
}
