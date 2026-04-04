<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        if (! $customer || $customer->email_verified_at) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please verify your email address before continuing.',
            'errors' => [
                'email' => ['Email verification is required.'],
            ],
            'data' => [
                'requires_verification' => true,
            ],
        ], 403);
    }
}
