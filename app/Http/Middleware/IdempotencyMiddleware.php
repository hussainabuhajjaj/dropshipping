<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'POST') {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $cacheKey = 'idempotency:' . sha1($request->path() . '|' . $key);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $content = $cached['content'] ?? '';
            $status = $cached['status'] ?? Response::HTTP_OK;
            $headers = $cached['headers'] ?? [];

            return response($content, $status, $headers);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ], now()->addMinutes(10));
        }

        return $response;
    }
}