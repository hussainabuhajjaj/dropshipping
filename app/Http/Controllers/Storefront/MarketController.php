<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Pricing\MarketPriceContext;
use App\Support\StorefrontMarket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal storefront market selector (Phase 1 — UAE).
 *
 * Stores ONLY a display preference in the session ('storefront_market_country').
 * It is never authoritative for checkout correctness: CheckoutController and
 * PaymentController re-pin the market from the submitted shipping country,
 * which always wins over this session value.
 */
class MarketController extends Controller
{
    /**
     * Country codes accepted by the Phase 1 selector. Unknown countries are
     * rejected so a typo cannot silently switch a visitor to a wrong market.
     */
    private const ALLOWED = ['CI', 'AE'];

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
        ]);

        $country = strtoupper($validated['country']);

        if (! in_array($country, self::ALLOWED, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unsupported market country.',
            ], 422);
        }

        $request->session()->put(StorefrontMarket::SESSION_KEY, $country);

        // Reset the request-scoped price cache so later reads in this same
        // request (if any) do not serve stale prices for the old market.
        app(MarketPriceContext::class)->setCountry($country);

        return response()->json([
            'ok' => true,
            'country' => $country,
            'market' => StorefrontMarket::marketForCountry($country),
            'label' => (string) config("markets.markets.".StorefrontMarket::marketForCountry($country).".label", $country),
        ]);
    }
}
