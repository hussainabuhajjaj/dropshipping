<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\Models\PaymentWebhook;
use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaystackWebhookController
{
    public function __invoke(Request $request, PaystackService $paystackService, PaymentService $paymentService): Response
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');

        if ($signature === '' || ! $paystackService->validateWebhook($payload, $signature)) {
            return response('Invalid signature', SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        $event = (string) $request->input('event', '');
        $data = $request->input('data');

        if ($event !== 'charge.success' || ! is_array($data)) {
            return response('Ignored', SymfonyResponse::HTTP_OK);
        }

        $reference = (string) ($data['reference'] ?? '');
        $eventId = (string) ($data['id'] ?? ('charge.success:' . $reference));

        if ($reference === '') {
            return response('Missing reference', SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $existingWebhook = PaymentWebhook::query()
            ->where('provider', 'paystack')
            ->where('external_event_id', $eventId)
            ->whereNotNull('processed_at')
            ->first();

        if ($existingWebhook) {
            return response('Already processed', SymfonyResponse::HTTP_OK);
        }

        DB::transaction(function () use ($reference, $eventId, $paystackService, $paymentService): void {
            $verification = $paystackService->verifyTransaction($reference);

            if (($verification['status'] ?? null) !== 'success') {
                return;
            }

            $payment = Payment::query()
                ->where('provider', 'paystack')
                ->where('provider_reference', $reference)
                ->with('order')
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            if ($payment->status === 'paid') {
                PaymentWebhook::query()->firstOrCreate(
                    ['external_event_id' => $eventId],
                    [
                        'provider' => 'paystack',
                        'payment_id' => $payment->id,
                        'payload' => $verification,
                        'processed_at' => now(),
                    ]
                );

                return;
            }

            $payment->forceFill([
                'meta' => array_merge($payment->meta ?? [], [
                    'paystack_webhook' => $verification,
                    'paystack_webhook_at' => now()->toISOString(),
                ]),
            ])->save();

            PaymentWebhook::query()->firstOrCreate(
                ['external_event_id' => $eventId],
                [
                    'provider' => 'paystack',
                    'payment_id' => $payment->id,
                    'payload' => $verification,
                    'processed_at' => now(),
                ]
            );

            $paymentService->markAsPaid($payment->fresh());
        });

        return response('OK', SymfonyResponse::HTTP_OK);
    }
}
