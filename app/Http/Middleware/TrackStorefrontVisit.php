<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\StorefrontSetting;
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
        $shouldTrack = $this->shouldTrack($request);

        if ($shouldTrack) {
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

        $response = $next($request);

        // Track immediately instead of relying solely on terminate(), which may not run in some production setups.
        // This does add a small DB write to the request, but makes analytics reliable.
        if ($shouldTrack && $this->isTrackableResponse($response)) {
            $visitorKey = $request->attributes->get('analytics_visitor_key');
            if (is_string($visitorKey) && $visitorKey !== '') {
                try {
                    $this->visitTrackingService->trackWebsiteRequest($request, $visitorKey);
                    $request->attributes->set('analytics_tracked', true);
                } catch (\Throwable $e) {
                    // Never break storefront traffic due to analytics failures.
                    \Log::warning('Website analytics tracking failed', [
                        'path' => $request->path(),
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ((bool) $request->attributes->get('analytics_tracked', false)) {
            return;
        }

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

        if ($this->isBot($request)) {
            return false;
        }

        // Visitor tracking is treated as "necessary/essential" for storefront health metrics and fraud/debugging.
        // This keeps visit counters working even when the user chooses "Essential only" (analytics = false).
        if (! $this->hasNecessaryConsent($request) && ! $this->shouldTrackWithoutConsent($request)) {
            return false;
        }

        // Storefront uses Inertia (XHR + JSON). Those navigations are real page views and should be tracked.
        // Still ignore other JSON/XHR requests (API, webhooks, etc).
        $isInertia = (bool) $request->header('X-Inertia');
        if (($request->expectsJson() || $request->isXmlHttpRequest()) && ! $isInertia) {
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

    private function isBot(Request $request): bool
    {
        $userAgent = mb_strtolower((string) $request->userAgent());
        if ($userAgent === '') {
            return true;
        }

        $botPatterns = [
            'googlebot', 'google-inspectiontool', 'google-sites',
            'bingbot', 'msnbot', 'ms Search',
            'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'yandeximages',
            'sogou', 'facebookexternalhit', 'facebot',
            'twitterbot', 'slackbot', 'discordbot', 'telegrambot',
            'semrushbot', 'ahrefsbot', 'majestic-12', 'mj12bot',
            'dotbot', 'rogerbot', 'exabot', 'gigabot',
            'adsbot', 'adsbot-google',
            'applebot',
            'bytespider', 'claude-web', 'gptbot', 'ccbot', 'anthropic-ai',
            'yeti', 'seznambot',
            'bingpreview',
            'google-site-verification',
            'pingdom', 'uptimerobot', 'newrelicpinger',
            'datadog', 'statuscake',
            'whatsapp', 'skypeuripreview',
            'curl', 'python-requests', 'python-urllib', 'wget',
            'scrapy', 'go-http-client', 'okhttp',
            'archive.org_bot', 'ia_archiver',
            'headless', 'phantomjs', 'puppeteer',
            'lighthouse', 'pagespeed',
            'chrome-lighthouse',
            'axios', 'aiohttp',
            'netcraftsurvey', 'zgrab',
        ];

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isTrackableResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        // Normal storefront page loads are HTML. Inertia navigations are JSON and include the X-Inertia header.
        return str_contains($contentType, 'text/html') || $response->headers->has('X-Inertia');
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

    private function hasNecessaryConsent(Request $request): bool
    {
        $raw = $request->cookie(self::CONSENT_COOKIE);

        // Essential cookies are allowed for basic storefront operation. If the user hasn't set preferences yet,
        // treat necessary as enabled.
        if (! is_string($raw) || trim($raw) === '') {
            return true;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && (bool) ($decoded['necessary'] ?? true);
    }

    private function shouldTrackWithoutConsent(Request $request): bool
    {
        if ($request->is(['*admin*', '*livewire*'])) {
            return false;
        }

        $settings = StorefrontSetting::latestForLocale(app()->getLocale());

        if (! $settings) {
            return false;
        }

        $siteEnabled = filter_var(env('SITE_ENABLED', true), FILTER_VALIDATE_BOOL);
        $comingSoonEnabled = (bool) $settings->coming_soon_enabled || ! $siteEnabled;

        return $comingSoonEnabled && ! session('is_developer', false);
    }
}
