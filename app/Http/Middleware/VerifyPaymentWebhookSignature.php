<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $provider = (string) $request->route('provider');
        $payload = $request->getContent();

        if ($provider === 'paystack') {
            $secret = config('services.paystack.webhook_secret') ?: config('services.paystack.secret_key');

            if (! $secret) {
                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Paystack webhook secret not configured.');
            }

            $signature = $request->header('X-Paystack-Signature');
            if (! $signature) {
                abort(Response::HTTP_UNAUTHORIZED, 'Missing Paystack webhook signature.');
            }

            $computed = hash_hmac('sha512', $payload, (string) $secret);

            if (! hash_equals($computed, (string) $signature)) {
                abort(Response::HTTP_UNAUTHORIZED, 'Invalid Paystack webhook signature.');
            }

            return $next($request);
        }

        if ($provider === 'korapay') {
            // Prefer services config (env-driven). Keep legacy config as a fallback.
            $secret = config('services.korapay.webhook_secret')
                ?: config('services.korapay.secret_key')
                ?: config('korapay.secret_key');

            if (! $secret) {
                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Korapay secret key not configured.');
            }

            $signature = $request->header('x-korapay-signature');
            if (! $signature) {
                abort(Response::HTTP_UNAUTHORIZED, 'Missing Korapay webhook signature.');
            }

            // Korapay signs ONLY the `data` object.
            $decoded = json_decode($payload, true);
            $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
            if (! is_array($data)) {
                abort(Response::HTTP_BAD_REQUEST, 'Invalid Korapay webhook payload.');
            }

            // Match Korapay docs: HMAC of JSON.stringify(req.body.data)
            // Preserve key order from the original JSON and avoid unicode escaping differences.
            $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! is_string($dataJson)) {
                abort(Response::HTTP_BAD_REQUEST, 'Invalid Korapay webhook payload.');
            }

            $computed = hash_hmac('sha256', $dataJson, (string) $secret);

            if (! hash_equals($computed, (string) $signature)) {
                abort(Response::HTTP_UNAUTHORIZED, 'Invalid Korapay webhook signature.');
            }

            return $next($request);
        }

        $secret = config('services.payments.webhook_secret');

        if (! $secret) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Payment webhook secret not configured.');
        }

        $signature = $request->header('X-Signature');
        if (! $signature) {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing webhook signature.');
        }

        $computed = hash_hmac('sha256', $payload, (string) $secret);

        if (! hash_equals($computed, (string) $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
