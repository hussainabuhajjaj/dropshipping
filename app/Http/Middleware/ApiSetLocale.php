<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ApiSetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_filter(array_map('trim', (array) config('services.translation_locales', ['en', 'fr'])));
        $supported = $supported ?: ['en', 'fr'];

        $headerLocale = $this->resolveFromHeader($request->header('Accept-Language', ''), $supported);

        $locale = $headerLocale;

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);

        $response = $next($request);

        $customer = $request->user('customer');
        if ($customer && (! $customer->locale || $customer->locale !== $locale)) {
            $customer->forceFill(['locale' => $locale])->save();
        }

        return $response;
    }

    private function resolveFromHeader(string $header, array $supported): ?string
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
            if (in_array($base, $supported, true)) {
                return $base;
            }
        }

        return null;
    }
}
