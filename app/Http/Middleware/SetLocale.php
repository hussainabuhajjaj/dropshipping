<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $sessionLocale = null;
        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
        }

        $cookieLocale = $request->cookie('locale');
        $headerLocale = $this->resolveFromHeader($request->header('Accept-Language', ''));

        $locale = $headerLocale
            ?? $sessionLocale
            ?? $cookieLocale;

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = $headerLocale;
        }

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);

        $response = $next($request);

        if ($request->hasSession() && $sessionLocale !== $locale) {
            $request->session()->put('locale', $locale);
        }

        if ($cookieLocale !== $locale) {
            $response->headers->setCookie(Cookie::forever('locale', $locale));
        }

        $customer = $request->user('customer');
        if ($customer && (! $customer->locale)) {
            $customer->forceFill(['locale' => $locale])->save();
        }

        return $response;
    }

    private function resolveFromHeader(string $header): ?string
    {
        if ($header === '') {
            return null;
        }

        $candidates = array_map(static function ($part) {
            $segment = trim($part);
            $segment = explode(';', $segment)[0] ?? '';
            return strtolower($segment);
        }, explode(',', $header));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $base = substr($candidate, 0, 2);
            if (in_array($base, self::SUPPORTED_LOCALES, true)) {
                return $base;
            }
        }

        return null;
    }
}
