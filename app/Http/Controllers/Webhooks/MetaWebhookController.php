<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetaWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($mode !== 'subscribe' || ! hash_equals((string) config('services.meta.verify_token'), $token)) {
            abort(403);
        }

        return response($challenge, 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-Hub-Signature-256');
        $secret = (string) config('services.meta.app_secret');

        if ($secret === '' || ! $this->validSignature($rawBody, $signature, $secret)) {
            abort(401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload) || ! isset($payload['object'])) {
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        ProcessMetaWebhookJob::dispatch($payload);

        return response()->json(['received' => true]);
    }

    private function validSignature(string $body, string $header, string $secret): bool
    {
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $header);
    }
}
