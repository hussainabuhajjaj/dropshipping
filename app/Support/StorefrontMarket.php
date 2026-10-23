<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Minimal market resolution for the storefront.
 *
 * Phase 1 rule (business decision): the shipping destination country is the
 * authoritative market selector. There is NO IP geolocation and no automatic
 * country detection. Resolution order:
 *   1. Explicit session override ('storefront_market_country') — set by the
 *      smallest-possible market selector (see MarketController).
 *   2. Request query parameter (?country=AE) — used for shareable links.
 *   3. Default market (CI) — preserves every existing behavior unchanged.
 */
final class StorefrontMarket
{
    public const CI = 'ci';
    public const UAE = 'uae';

    public const SESSION_KEY = 'storefront_market_country';

    /** Country codes that map to the UAE market. */
    private const UAE_COUNTRIES = ['AE', 'ARE', 'UAE'];

    /** Country codes that map to the CI market. */
    private const CI_COUNTRIES = ['CI', 'CIV'];

    public static function defaultCountry(): string
    {
        return (string) config('markets.default_country', 'CI');
    }

    public static function resolveCountry(?Request $request = null): string
    {
        $sessionCountry = session(self::SESSION_KEY);
        if (is_string($sessionCountry) && trim($sessionCountry) !== '') {
            return strtoupper(trim($sessionCountry));
        }

        if ($request !== null) {
            $queryCountry = $request->query('country');
            if (is_string($queryCountry) && preg_match('/^[A-Za-z]{2}$/', $queryCountry)) {
                return strtoupper($queryCountry);
            }
        }

        return self::defaultCountry();
    }

    public static function marketForCountry(?string $country): string
    {
        $code = strtoupper(trim((string) $country));

        if (in_array($code, self::UAE_COUNTRIES, true)) {
            return self::UAE;
        }

        // Everything else (including CI and unknown countries) keeps the
        // existing single-market behavior.
        return self::CI;
    }

    public static function currentMarket(?Request $request = null): string
    {
        return self::marketForCountry(self::resolveCountry($request));
    }

    public static function isUaeCountry(?string $country): bool
    {
        return self::marketForCountry($country) === self::UAE;
    }
}
