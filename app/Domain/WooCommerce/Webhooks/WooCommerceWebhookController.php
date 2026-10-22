<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Webhooks;

use App\Domain\WooCommerce\Jobs\ProcessWooWebhookJob;
use App\Domain\WooCommerce\Models\WooCommerceWebhookLog;
use App\Domain\WooCommerce\Webhooks\WooCommerceWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WooCommerceWebhookController extends Controller
{
    public function __construct(
        private readonly WooCommerceWebhookVerifier $verifier,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('woocommerce.enabled', false)) {
            return response()->json(['status' => 'disabled'], 200);
        }

        $rawPayload = $request->getContent();
        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return response()->json(['status' => 'invalid_payload'], 422);
        }

        $eventType = $request->header('X-WC-Webhook-Topic', '');
        $signature = $request->header('X-WC-Webhook-Signature', '');
        $deliveryId = $request->header('X-WC-Webhook-Delivery-ID', '');
        $resource = $request->header('X-WC-Webhook-Resource', '');
        $event = $request->header('X-WC-Webhook-Event', '');

        if ($eventType === '' || $signature === '') {
            Log::warning('WooCommerce webhook missing headers', [
                'event_type' => $eventType,
                'has_signature' => $signature !== '',
            ]);

            return response()->json(['status' => 'missing_headers'], 400);
        }

        if (! $this->verifier->verify($rawPayload, $signature)) {
            Log::warning('WooCommerce webhook signature verification failed');

            return response()->json(['status' => 'invalid_signature'], 401);
        }

        if ($deliveryId !== '') {
            $existing = WooCommerceWebhookLog::where('delivery_id', $deliveryId)->exists();

            if ($existing) {
                Log::info('WooCommerce webhook already processed (duplicate delivery)', [
                    'delivery_id' => $deliveryId,
                    'event_type' => $eventType,
                ]);

                WooCommerceWebhookLog::where('delivery_id', $deliveryId)->update([
                    'payload' => $payload,
                    'updated_at' => now(),
                ]);

                return response()->json(['status' => 'duplicate'], 200);
            }
        }

        ProcessWooWebhookJob::dispatch(
            $eventType,
            $payload,
            $rawPayload,
            $signature,
            $deliveryId,
            $resource,
            $event,
        );

        return response()->json(['status' => 'queued'], 200);
    }
}
