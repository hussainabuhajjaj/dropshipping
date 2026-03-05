<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetUserPreferences
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Set currency from session or user preferences
        $currency = session('user_currency', 'USD');

        if (auth('customer')->check()) {
            $user = auth('customer')->user();
            $currency = $user->preferred_currency ?? $currency;

            // Update session if user preference exists
            if ($user->preferred_currency && session('user_currency') !== $user->preferred_currency) {
                session(['user_currency' => $user->preferred_currency]);
            }
        }

        // Share currency with all views
        view()->share('current_currency', $currency);

        // Set language from session or user preferences
        $language = session('locale', 'en');

        if (auth('customer')->check()) {
            $user = auth('customer')->user();
            $language = $user->preferred_language ?? $language;

            // Update session if user preference exists
            if ($user->preferred_language && session('locale') !== $user->preferred_language) {
                session(['locale' => $user->preferred_language]);
            }
        }

        // Set application locale
        App::setLocale($language);

        // Share language with all views
        view()->share('current_language', $language);

        return $next($request);
    }
}
