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

        // Korapay signs ONLY the `data` object:
        // HMAC_SHA256(JSON.stringify(req.body.data), secretKey)
        $raw = (string) $request->getContent();
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;

        if (! is_array($data)) {
            abort(Response::HTTP_BAD_REQUEST, 'Invalid Korapay webhook payload.');
        }

        // Match Korapay docs: JSON.stringify does not escape slashes.
        // Preserve key order from the original JSON decode.
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
}
