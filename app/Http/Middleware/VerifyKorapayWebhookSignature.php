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
            // Per Korapay docs best-practice: respond 200 to stop retries; do not process.
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'missing_signature'], Response::HTTP_OK);
        }

        // Korapay signs ONLY the `data` object:
        // HMAC_SHA256(JSON.stringify(req.body.data), secretKey)
        $raw = (string) $request->getContent();
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;

        if (! is_array($data)) {
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'invalid_payload'], Response::HTTP_OK);
        }

        // Korapay recommends forcing serialize_precision to -1 to avoid float encoding issues.
        // This makes json_encode mirror JavaScript JSON.stringify more consistently.
        // (This is process-wide; safe for a webhook request.)
        @ini_set('serialize_precision', '-1');

        // Match Korapay docs: JSON.stringify does not escape slashes.
        // Preserve key order from the original JSON decode.
        $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($dataJson)) {
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'invalid_payload'], Response::HTTP_OK);
        }

        $computed = hash_hmac('sha256', $dataJson, (string) $secret);

        if (! hash_equals($computed, (string) $signature)) {
            // Per Korapay docs best-practice: respond 200 to stop retries; do not process.
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'invalid_signature'], Response::HTTP_OK);
        }

        return $next($request);
    }
}
