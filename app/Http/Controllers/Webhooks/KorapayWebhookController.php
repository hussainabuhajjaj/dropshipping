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
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $eventId = $payload['event_id'] ?? $payload['id'] ?? $data['id'] ?? $data['reference'] ?? $request->header('X-Event-Id');

        if (! $eventId) {
            return response()->json(['error' => 'Missing event id'], Response::HTTP_BAD_REQUEST);
        }

        $normalized = [
            'event_id' => $eventId,
            'provider_reference' => $data['reference'] ?? null,
            'transaction_id' => $data['id'] ?? null,
            'order_number' => $data['metadata']['order_number'] ?? $payload['order_number'] ?? null,
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
