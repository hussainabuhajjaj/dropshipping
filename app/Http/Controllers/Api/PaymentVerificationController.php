<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Paystack\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentVerificationController
{
    public function __construct(
        private PaymentService $paymentService,
        private PaystackService $paystackService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $reference = $request->input('reference');
        
        if (!$reference) {
            return response()->json([
                'success' => false,
                'message' => 'Reference is required',
            ], 400);
        }

        try {
            // Verify payment with Paystack
            $verification = $this->paystackService->verifyTransaction($reference);
            
            Log::info('Payment verification requested', [
                'reference' => $reference,
                'verification_status' => $verification['status'],
                'verification_data' => $verification,
            ]);

            // Find payment record
            $payment = \App\Domain\Payments\Models\Payment::where('provider_reference', $reference)
                ->with('order')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Apply status based on verification
            $payload = [
                'event_id' => 'verify:' . $reference,
                'provider_reference' => $reference,
                'transaction_id' => $verification['id'],
                'order_number' => $verification['metadata']['order_number'] ?? null,
                'status' => $verification['status'],
                'amount' => $verification['amount'],
            ];

            $this->paymentService->applyStatusFromPayload($payment, $payload);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => [
                    'payment_status' => $payment->status,
                    'order_status' => $payment->order?->status,
                    'order_payment_status' => $payment->order?->payment_status,
                    'verification_status' => $verification['status'],
                    'order_number' => $payment->order?->number,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Payment verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
