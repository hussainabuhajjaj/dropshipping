<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\PaymentService;
use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Models\Cart;
use App\Domain\Payments\Models\Payment;
use App\Services\AbandonedCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackCallbackController
{
    public function __invoke(
        Request $request,
        PaymentService $paymentService,
        PaystackService $paystackService
    ): RedirectResponse {
        $reference = (string) (
            $request->query('reference')
            ?? $request->query('trxref')
            ?? ''
        );

        if ($reference === '') {
            Log::warning('Paystack callback missing reference', [
                'query_params' => $request->query(),
                'ip' => $request->ip(),
            ]);
            
            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'Missing payment reference.']);
        }

        Log::info('Paystack callback received', [
            'reference' => $reference,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Find payment record
        $payment = Payment::query()
            ->where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->with('order')
            ->latest('id')
            ->first();

        if (! $payment) {
            Log::warning('Paystack callback payment not found', [
                'reference' => $reference,
                'query' => $request->query(),
            ]);

            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'Payment not found.']);
        }

        // Already paid (idempotent safe) - just redirect to confirmation
        if ($payment->status === 'paid' && $payment->order) {
            Log::info('Payment already paid, clearing cart', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order->id,
                'customer_id' => $payment->order->customer_id,
            ]);
            
            $this->clearCustomerCart($payment);

            return $this->redirectToConfirmation($payment, 'Payment confirmed.');
        }

        try {
            // Verify payment with Paystack (NO signature validation for GET callback)
            $verification = $paystackService->verifyTransaction($reference);

            Log::info('Paystack verification result', [
                'reference' => $reference,
                'verification_data' => $verification,
                'payment_id' => $payment->id,
            ]);

            if ($verification['status'] === 'success') {
                // Update payment status
                $payload = [
                    'event_id' => 'callback:' . $reference,
                    'provider_reference' => $reference,
                    'transaction_id' => $verification['id'],
                    'order_number' => $verification['metadata']['order_number'] ?? null,
                    'status' => $verification['status'],
                    'amount' => $verification['amount'],
                ];

                $paymentService->applyStatusFromPayload($payment, $payload);

                // Clear cart only after successful payment
                $this->clearCustomerCart($payment);

                Log::info('Payment successfully updated via callback', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order?->id,
                    'order_number' => $payment->order?->number,
                ]);

                return $this->redirectToConfirmation($payment, 'Payment confirmed successfully.');
            }

            // Payment not successful according to Paystack
            Log::warning('Payment verification failed - Paystack status not success', [
                'reference' => $reference,
                'verification_status' => $verification['status'],
                'payment_id' => $payment->id,
                'local_payment_status' => $payment->status,
            ]);

            return $this->redirectToConfirmation($payment, 'Payment verification failed. Please contact support.');

        } catch (\Throwable $e) {
            Log::error('Paystack callback verification failed with exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_id' => $payment->id,
                'local_payment_status' => $payment->status,
            ]);

            // If payment is already paid locally but verification failed, don't show error
            if ($payment->status === 'paid') {
                return $this->redirectToConfirmation($payment, 'Payment confirmed.');
            }

            // For verification errors, still redirect to confirmation with error message
            return $this->redirectToConfirmation($payment, 'Payment verification failed. Please try again or contact support.');
        }
    }

    private function redirectToConfirmation(Payment $payment, string $message): RedirectResponse
    {
        return redirect()
            ->route('orders.confirmation', ['number' => $payment->order->number])
            ->with('status', $message);
    }

    private function clearCustomerCart(Payment $payment): void
    {
        $order = $payment->order;
        $customerId = $order?->customer_id;

        if (! $customerId) {
            return;
        }

        $cart = Cart::query()->where('user_id', $customerId)->first();
        if (! $cart) {
            return;
        }

        Log::info('Clearing customer cart after successful Paystack payment', [
            'reference' => $payment->provider_reference,
            'payment_id' => $payment->id,
            'order_id' => $order?->id,
            'customer_id' => $customerId,
            'cart_id' => $cart->id,
        ]);

        $cart->emptyCart();
        app(AbandonedCartService::class)->markRecovered();
    }
}
