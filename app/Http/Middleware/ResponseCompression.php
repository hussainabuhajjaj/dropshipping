<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseCompression
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only compress JSON responses larger than 1KB
        if ($response->headers->get('Content-Type') === 'application/json' &&
            strlen($response->getContent()) > 1024 &&
            $request->header('Accept-Encoding') &&
            str_contains($request->header('Accept-Encoding'), 'gzip')) {

            $compressed = gzencode($response->getContent(), 6);
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', strlen($compressed));
        }

        return $response;
    }
}
