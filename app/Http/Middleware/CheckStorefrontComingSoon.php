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
        // $filamentPath = trim((string) config('filament.path', 'admin'), '/');
        // $segment = (string) $request->segment(1);
        // $routeName = $request->route()?->getName();

        // if (
        //     $segment === 'admin' ||
        //     $segment === 'filament' ||
        //     ($filamentPath !== '' && $segment === $filamentPath) ||
        //     $request->is('admin*') ||
        //     $request->is('filament*') ||
        //     ($filamentPath !== '' && ($request->is($filamentPath) || $request->is($filamentPath . '/*'))) ||
        //     ($routeName && str_starts_with($routeName, 'filament.')) ||
        //     $request->routeIs('filament.*') ||
        //     $request->is('api*') ||
        //     $request->is('_debugbar*') ||
        //     $request->is('newsletter/subscribe') ||
        //     $request->is('newsletter/unsubscribe*') ||
        //     $request->is('newsletter/track/*') ||
        //     $request->routeIs('coming-soon')
        // ) {
        //     return $next($request);
        // }
        $settings = StorefrontSetting::latestForLocale(app()->getLocale());
       if(!$settings){
        return $next($request);
       }


        // return Inertia::render('ComingSoon', [
        //     'title' => $settings->coming_soon_title,
        //     'message' => $settings->coming_soon_message,
        //     'image' => $settings->coming_soon_image,
        //     'cta_label' => $settings->coming_soon_cta_label,
        //     'cta_url' => $settings->coming_soon_cta_url,
        // ])->toResponse($request)->setStatusCode(503);
        if ($request->is(['*admin*','*livewire*'])) {
            return $next($request);
        }
        $is_site_enabled = env('SITE_ENABLED', true);
        $is_developer = session('is_developer', false);
        $is_developer_expires_at = session('is_developer_expires_at', null);
        $is_developer_active = now()->timestamp < session('is_developer_expires_at');

//        dd($is_developer, now()->timestamp, $is_developer_expires_at, $is_developer_active);

//        && (!$is_developer || isset($is_developer_expires_at) || !$is_developer_active)
        if (!$is_site_enabled && (!$is_developer && !$is_developer_expires_at && !$is_developer_active)) {
            if ($request->is('*coming-soon') || $request->is('login-developer') 
                || $request->is('logout-developer') || $request->is('*pay*') || $request->is('*uploadFile*')) {
                return $next($request);
            }
            // return redirect('/coming-soon');
             return Inertia::render('ComingSoon', [
            'title' => $settings->coming_soon_title,
            'message' => $settings->coming_soon_message,
            'image' => $settings->coming_soon_image,
            'cta_label' => $settings->coming_soon_cta_label,
            'cta_url' => $settings->coming_soon_cta_url,
        ])->toResponse($request)->setStatusCode(503);
        }

        return $next($request);
    }
}
