<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKorapayWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.korapay.webhook_secret') ?: config('services.korapay.secret_key');

        if (! $secret) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Korapay webhook secret not configured.');
        }

        $signature = $request->header('x-korapay-signature');
        if (! $signature) {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing Korapay webhook signature.');
        }

        $computed = hash_hmac('sha256', (string) $request->getContent(), (string) $secret);

        if (! hash_equals($computed, (string) $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid Korapay webhook signature.');
        }

        return $next($request);
    }
}
