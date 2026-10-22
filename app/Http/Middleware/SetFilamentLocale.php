<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetFilamentLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        app()->setLocale('en');
        config(['app.locale' => 'en']);

        return $next($request);
    }
}
