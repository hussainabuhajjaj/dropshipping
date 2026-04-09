<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class KorapayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $paymentService): JsonResponse
    {
        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $eventId = $payload['event_id']
            ?? $payload['id']
            ?? $data['id']
            ?? $request->header('X-Event-Id')
            // Fallback: some events don't include an explicit id; make a stable-ish id from event+reference
            ?? (($event !== '' && ! empty($data['reference'] ?? null)) ? ($event . ':' . (string) $data['reference']) : null);

        if (! $eventId) {
            // Last resort: hash the raw content so Korapay retries don't fan out into duplicates.
            $eventId = 'hash:' . hash('sha256', (string) $request->getContent());
        }

        // If you're using this endpoint for multiple Korapay features, you might get events that
        // are not order payments (e.g., transfers/refunds). Ignore safely with 200 so Korapay stops retrying.
        if ($event !== '' && ! str_starts_with($event, 'charge.')) {
            return response()->json([
                'success' => true,
                'ignored' => true,
                'event' => $event,
                'event_id' => $eventId,
            ]);
        }

        $normalized = [
            'event_id' => $eventId,
            'provider_reference' => $data['reference'] ?? null,
            'transaction_id' => $data['id'] ?? null,
            // Korapay may return merchant metadata under `metadata` or `meta` depending on channel/version.
            'order_number' => $data['metadata']['order_number']
                ?? $data['meta']['order_number']
                ?? $payload['order_number']
                ?? null,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? null,
            'korapay' => $payload,
        ];

        try {
            $payment = $paymentService->handleWebhook('korapay', $eventId, $normalized);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'order_id' => $payment->order_id,
            'order_payment_status' => $payment->order?->payment_status,
        ]);
    }
}
