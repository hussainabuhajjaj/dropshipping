<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Analytics\VisitTrackingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class TrackStorefrontVisit
{
    private const CONSENT_COOKIE = 'storefront_cookie_consent';

    public function __construct(private readonly VisitTrackingService $visitTrackingService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldTrack($request)) {
            $visitorKey = (string) ($request->cookie(VisitTrackingService::WEBSITE_COOKIE) ?: $this->visitTrackingService->generateVisitorKey());
            $request->attributes->set('analytics_visitor_key', $visitorKey);

            if (! $request->cookies->has(VisitTrackingService::WEBSITE_COOKIE)) {
                Cookie::queue(cookie(
                    VisitTrackingService::WEBSITE_COOKIE,
                    $visitorKey,
                    60 * 24 * 365,
                    '/',
                    null,
                    false,
                    false,
                    false,
                    'lax'
                ));
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldTrack($request) || ! $this->isTrackableResponse($response)) {
            return;
        }

        $visitorKey = $request->attributes->get('analytics_visitor_key');
        if (! is_string($visitorKey) || $visitorKey === '') {
            return;
        }

        $this->visitTrackingService->trackWebsiteRequest($request, $visitorKey);
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (! $this->hasAnalyticsConsent($request)) {
            return false;
        }

        if ($request->expectsJson() || $request->isXmlHttpRequest()) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        if ($path === '' || str_starts_with($path, 'admin') || str_starts_with($path, 'api/')) {
            return $path === '';
        }

        foreach (['webhooks/', 'media/proxy', 'sitemap.xml', 'robots.txt', '_debugbar'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function isTrackableResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }

    private function hasAnalyticsConsent(Request $request): bool
    {
        $raw = $request->cookie(self::CONSENT_COOKIE);
        if (! is_string($raw) || trim($raw) === '') {
            return false;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && (bool) ($decoded['analytics'] ?? false);
    }
}
