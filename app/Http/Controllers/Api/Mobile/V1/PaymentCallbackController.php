<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Domain\Payments\PaymentService;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentCallbackController extends ApiController
{
    public function handle(Request $request, PaymentService $paymentService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string|max:255',
            'provider' => 'required|in:korapay,stripe,paypal',
            'status' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $data = $validator->validated();
        $reference = $data['reference'];
        $provider = $data['provider'];

        $payment = Payment::query()
            ->where('provider', $provider)
            ->where('provider_reference', $reference)
            ->first();

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        try {
            // Verify payment with provider
            $verification = match ($provider) {
                'korapay' => $paymentService->verifyKorapay($reference),
                default => throw new \InvalidArgumentException("Payment provider '{$provider}' is not supported"),
            };

            // Update payment meta with verification data
            $payment->update([
                'meta' => array_merge($payment->meta ?? [], [
                    'mobile_callback' => $data['data'] ?? [],
                    'callback_at' => now()->toISOString(),
                ]),
            ]);

            // Handle payment status
            $status = $verification->status ?? 'pending';
            
            if ($status === 'paid') {
                $paymentService->markAsPaid($payment);

                return $this->success([
                    'status' => 'success',
                    'payment_status' => 'paid',
                    'order_status' => $verification->order?->status,
                    'order_number' => $verification->order?->number,
                    'reference' => $reference,
                    'message' => 'Payment confirmed successfully',
                ]);
            }

            if (in_array($status, ['failed', 'cancelled'], true)) {
                $payment->update(['status' => 'failed']);

                return $this->success([
                    'status' => 'failed',
                    'payment_status' => 'failed',
                    'order_status' => $verification->order?->status,
                    'order_number' => $verification->order?->number,
                    'reference' => $reference,
                    'message' => 'Payment failed or cancelled',
                ]);
            }

            return $this->success([
                'status' => 'pending',
                'payment_status' => 'pending',
                'order_status' => $verification->order?->status,
                'order_number' => $verification->order?->number,
                'reference' => $reference,
                'message' => 'Payment is being processed',
            ]);

        } catch (\Throwable $e) {
            \Log::error('Mobile payment callback failed', [
                'provider' => $provider,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Payment verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle payment cancellation
     */
    public function cancel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string|max:255',
            'provider' => 'required|in:korapay,stripe,paypal',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $data = $validator->validated();
        
        $payment = Payment::query()
            ->where('provider', $data['provider'])
            ->where('provider_reference', $data['reference'])
            ->first();

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        $payment->update([
            'status' => 'cancelled',
            'meta' => array_merge($payment->meta ?? [], [
                'cancelled_at' => now()->toISOString(),
                'cancelled_by' => 'mobile_user',
            ]),
        ]);

        return $this->success([
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'order_number' => $payment->order?->number,
            'reference' => $data['reference'],
            'message' => 'Payment was cancelled',
        ]);
    }
}
