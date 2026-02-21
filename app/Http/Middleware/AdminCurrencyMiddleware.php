<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AdminCurrencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Set locale for consistent USD formatting in admin panel
        App::setLocale('en_US');
        
        // Ensure all currency formatting uses USD in admin panel
        config(['app.currency' => 'USD']);
        config(['filament.currency' => 'USD']);
        
        return $next($request);
    }
}
