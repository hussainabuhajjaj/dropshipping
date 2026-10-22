<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTrackingWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.tracking.webhook_secret');

        if (! $secret) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Tracking webhook secret not configured.');
        }

        $signature = $request->header('X-Signature');
        if (! $signature) {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing webhook signature.');
        }

        $payload = $request->getContent();
        $computed = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($computed, (string) $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
