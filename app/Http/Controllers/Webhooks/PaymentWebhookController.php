<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\PaymentService;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __invoke(string $provider, Request $request, PaymentService $paymentService): JsonResponse
    {
        $payload = $request->all();

        if ($provider === 'paystack') {
            $payload = $this->normalizePaystackPayload($payload);
        }

        if ($provider === 'korapay') {
            $payload = $this->normalizeKorapayPayload($payload);
        }

        $eventId = $payload['event_id'] ?? $payload['id'] ?? $request->header('X-Event-Id');

        if (! $eventId) {
            return response()->json(['error' => 'Missing event id'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payment = $paymentService->handleWebhook($provider, (string) $eventId, $payload);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'order_id' => $payment->order_id,
            'order_payment_status' => $payment->order->payment_status,
        ]);
    }

    private function normalizePaystackPayload(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = $data['reference'] ?? null;
        $orderNumber = $data['metadata']['order_number'] ?? null;

        if (! $orderNumber && $reference) {
            $payment = Payment::query()
                ->where('provider', 'paystack')
                ->where('provider_reference', $reference)
                ->with('order')
                ->first();
            $orderNumber = $payment?->order?->number;
        }

        return array_merge($payload, [
            'event_id' => $data['id'] ?? $reference ?? $payload['event'] ?? null,
            'provider_reference' => $reference,
            'transaction_id' => $data['id'] ?? null,
            'order_number' => $orderNumber,
            'amount' => isset($data['amount']) ? ((float) $data['amount'] / 100) : null,
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? null,
            'paystack' => $payload,
        ]);
    }

    private function normalizeKorapayPayload(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = $data['reference'] ?? null;
        $event = (string) ($payload['event'] ?? 'charge');

        $orderNumber = $data['metadata']['order_number']
            ?? $data['meta']['order_number']
            ?? null;

        if (! $orderNumber && $reference) {
            $payment = Payment::query()
                ->where('provider', 'korapay')
                ->where('provider_reference', $reference)
                ->with('order')
                ->first();
            $orderNumber = $payment?->order?->number;
        }

        $eventId = $data['id'] ?? ($reference ? ($event . ':' . $reference) : null);

        return array_merge($payload, [
            'event_id' => $eventId,
            'provider_reference' => $reference,
            'transaction_id' => $data['id'] ?? null,
            'order_number' => $orderNumber,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? null,
            'korapay' => $payload,
        ]);
    }
}
