<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMobile
{
    /**
     * Mobile User-Agent patterns.
     */
    private const MOBILE_PATTERNS = [
        '/Mobile|iP(hone|od|ad)|Android|BlackBerry|IEMobile|Kindle|NetFront|Silk-Accelerated|(hpw|web)OS|Fennec|Minimo|Opera M(obi|ini)|Blazer|Dolfin|Dolphin|Skyfire|Zune/',
    ];

    /**
     * Bot/crawler patterns to skip.
     */
    private const BOT_PATTERNS = [
        '/bot|crawl|spider|scraper|curl|wget|facebookexternalhit|Twitterbot|Pinterest|Googlebot|Bingbot|Slurp|DuckDuckBot|Baidu|Yandex|Sogou|Exabot|facebot|ia_archiver/i',
    ];

    /**
     * Path prefixes to skip.
     */
    private const SKIP_PATHS = [
        'api',
        'admin',
        'filament',
        'horizon',
        'nova',
        'telescope',
        'webhooks',
        'paystack',
        '_debugbar',
        'sanctum',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (!$this->shouldSkip($request)) {
            $mobileDomain = config('app.mobile_subdomain');

            if (!empty($mobileDomain)) {
                if (!$this->isAlreadyOnMobileDomain($host, $mobileDomain)) {
                    $mobileUrl = $this->buildMobileUrl($request, $mobileDomain);

                    return redirect()->away($mobileUrl, 302);
                }
            }
        }

        $response = $this->addVaryHeader($next($request));

        if ($response->getStatusCode() === 302 && $request->method() === 'POST') {
            $response->setStatusCode(303);
        }

        return $response;
    }

    private function addVaryHeader(Response $response): Response
    {
        $vary = $response->headers->get('Vary', '');
        $values = array_filter(array_map('trim', explode(',', $vary)));
        if (!in_array('User-Agent', $values, true)) {
            $values[] = 'User-Agent';
        }
        $response->headers->set('Vary', implode(', ', $values));

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->header('X-Inertia')) {
            return true;
        }

        if ($request->query('mobile') === '0') {
            return true;
        }

        if ($request->query('desktop') === '1') {
            return true;
        }

        if ($request->cookies->get('prefer_desktop') === '1') {
            return true;
        }

        $path = $request->path();

        foreach (self::SKIP_PATHS as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        if ($request->isMethod('OPTIONS')) {
            return true;
        }

        $userAgent = $request->userAgent();

        if (empty($userAgent)) {
            return true;
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        foreach (self::MOBILE_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return false;
            }
        }

        return true;
    }

    private function isAlreadyOnMobileDomain(string $host, string $mobileDomain): bool
    {
        $mobileHost = parse_url($mobileDomain, PHP_URL_HOST) ?: $mobileDomain;

        return $host === $mobileHost;
    }

    private function buildMobileUrl(Request $request, string $mobileDomain): string
    {
        $mobileBase = rtrim($mobileDomain, '/');

        $uri = $request->getRequestUri();

        if (empty($uri) || $uri === '/') {
            return $mobileBase;
        }

        $query = $request->query();

        unset($query['mobile'], $query['desktop']);

        $path = $uri;

        if (!empty($query)) {
            $path .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
        }

        return $mobileBase . $path;
    }
}
