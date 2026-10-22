<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Payments\PaymentService;
use App\Domain\Payments\Models\Payment;
use App\Infrastructure\Payments\Paystack\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentVerificationController extends ApiController
{
    public function __construct(
        private PaymentService $paymentService,
        private PaystackService $paystackService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $reference = $request->input('reference');

        if (! $reference) {
            return $this->error('Reference is required', Response::HTTP_BAD_REQUEST);
        }

        try {
            $verification = $this->paystackService->verifyTransaction($reference);

            Log::info('Payment verification requested', [
                'reference' => $reference,
                'verification_status' => $verification['status'],
                'verification_data' => $verification,
            ]);

            $payment = Payment::where('provider_reference', $reference)
                ->with('order')
                ->first();

            if (! $payment) {
                return $this->error('Payment not found', Response::HTTP_NOT_FOUND);
            }

            $payload = [
                'event_id' => 'verify:' . $reference,
                'provider_reference' => $reference,
                'transaction_id' => $verification['id'],
                'order_number' => $verification['metadata']['order_number'] ?? null,
                'status' => $verification['status'],
                'amount' => $verification['amount'],
            ];

            $this->paymentService->applyStatusFromPayload($payment, $payload);

            return $this->success([
                'payment_status' => $payment->status,
                'order_status' => $payment->order?->status,
                'order_payment_status' => $payment->order?->payment_status,
                'verification_status' => $verification['status'],
                'order_number' => $payment->order?->number,
            ], 'Payment verified successfully');

        } catch (\Throwable $e) {
            Log::error('Payment verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            $message = 'Verification failed';
            if (config('app.debug')) {
                $message = $e->getMessage();
            }

            return $this->error($message, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
