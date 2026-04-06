<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\StorefrontSetting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckStorefrontComingSoon
{
    public function handle(Request $request, Closure $next)
    {
        $settings = StorefrontSetting::latestForLocale(app()->getLocale());

        if (! $settings) {
            return $next($request);
        }

        if ($request->is(['*admin*', '*livewire*'])) {
            return $next($request);
        }

        $siteEnabled = filter_var(env('SITE_ENABLED', true), FILTER_VALIDATE_BOOL);
        $comingSoonEnabled = (bool) $settings->coming_soon_enabled || ! $siteEnabled;

        if (! $comingSoonEnabled) {
            return $next($request);
        }

        $developerSessionEnabled = (bool) session('is_developer', false);
        $developerExpiresAt = session('is_developer_expires_at');
        $developerIsActive = $developerSessionEnabled
            && (
                ! is_numeric($developerExpiresAt)
                || now()->timestamp < (int) $developerExpiresAt
            );

        if ($developerIsActive) {
            return $next($request);
        }

        if (
            $request->routeIs('coming-soon', 'newsletter.subscribe', 'newsletter.unsubscribe', 'newsletter.track.*')
            || $request->is('login-developer', 'logout-developer', '*pay*', '*uploadFile*')
        ) {
            return $next($request);
        }

        return Inertia::render('ComingSoon', [
            'title' => $settings->coming_soon_title,
            'message' => $settings->coming_soon_message,
            'image' => $settings->coming_soon_image,
            'cta_label' => $settings->coming_soon_cta_label,
            'cta_url' => $settings->coming_soon_cta_url,
            'storefront' => [
                'social_links' => $settings->social_links,
                'brand_name' => $settings->brand_name,
            ],
        ])->toResponse($request)->setStatusCode(503);

        return $next($request);
    }
}
