<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\PaymentService;
use App\Models\Cart;
use App\Domain\Payments\Models\Payment;
use App\Services\AbandonedCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackCallbackController
{
    public function __invoke(Request $request, PaymentService $paymentService): RedirectResponse
    {
        $reference = (string) (
            $request->query('reference')
            ?? $request->query('trxref')
            ?? ''
        );

        if ($reference === '') {
            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'Missing payment reference.']);
        }

        $payment = \App\Domain\Payments\Models\Payment::query()
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

        Log::info('Paystack callback received', [
            'reference' => $reference,
            'payment_id' => $payment->id,
            'order_id' => $payment->order?->id,
            'payment_status' => $payment->status,
            'order_payment_status' => $payment->order?->payment_status,
            'query' => $request->query(),
        ]);

        // Already paid (idempotent safe)
        if ($payment->status === 'paid' && $payment->order) {
            $this->clearCustomerCart($payment);

            return $this->redirectToConfirmation($payment, 'Payment confirmed.');
        }

        try {
            $payment = $paymentService
                ->verifyPaystack($reference)
                ->load('order');

            Log::info('Paystack callback verification result', [
                'reference' => $reference,
                'payment_id' => $payment->id,
                'order_id' => $payment->order?->id,
                'payment_status' => $payment->status,
                'order_payment_status' => $payment->order?->payment_status,
            ]);

        } catch (\Throwable $e) {
            Log::error('Paystack callback verification failed', [
                'reference' => $reference,
                'payment_id' => $payment->id,
                'order_id' => $payment->order?->id,
                'error' => $e->getMessage(),
            ]);

            $payment = $payment->fresh(['order']) ?? $payment;

            // Always verify payment status with Paystack before redirect
            try {
                $paystackService = app(\App\Infrastructure\Payments\Paystack\PaystackService::class);
                $verification = $paystackService->verifyTransaction($reference);
                
                Log::info('Paystack verification before redirect', [
                    'reference' => $reference,
                    'payment_id' => $payment->id,
                    'verification_status' => $verification['status'],
                    'current_payment_status' => $payment->status,
                ]);
                
                // Update payment status based on verification
                if ($verification['status'] === 'success' && $payment->status !== 'paid') {
                    $paymentService->markAsPaid($payment);
                    Log::info('Payment status updated to paid via verification', [
                        'payment_id' => $payment->id,
                        'reference' => $reference,
                    ]);
                }
                
                // Clear cart if payment is successful
                if ($verification['status'] === 'success' && $payment->order) {
                    $this->clearCustomerCart($payment);
                }
                
            } catch (\Throwable $verifyError) {
                Log::error('Payment verification before redirect failed', [
                    'reference' => $reference,
                    'payment_id' => $payment->id,
                    'error' => $verifyError->getMessage(),
                ]);
            }

            if ($payment->status === 'paid' && $payment->order) {
                return $this->redirectToConfirmation($payment, 'Payment confirmed.');
            }

            if (in_array($payment->status, ['pending', 'authorized'], true) && $payment->order) {
                return $this->redirectToConfirmation($payment, 'Payment is pending confirmation.');
            }

            if ($payment->order) {
                return $this->redirectToConfirmation($payment, null, 'Payment verification failed.');
            }

            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'Payment verification failed.']);
        }

        if ($payment->status === 'paid' && $payment->order) {
            $this->clearCustomerCart($payment);

            return $this->redirectToConfirmation($payment, 'Payment confirmed.');
        }

        if (in_array($payment->status, ['pending', 'authorized'], true) && $payment->order) {
            return $this->redirectToConfirmation($payment, 'Payment is pending confirmation.');
        }

        if ($payment->status === 'failed' && $payment->order) {
            return $this->redirectToConfirmation($payment, null, 'Payment failed.');
        }

        return $this->redirectToConfirmation($payment, null, 'Payment not completed.');
    }

    private function redirectToConfirmation(Payment $payment, ?string $status = null, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('orders.confirmation', [
            'number' => $payment->order?->number ?? '',
        ]);

        if ($status !== null) {
            return $redirect->with('status', $status);
        }

        if ($error !== null) {
            return $redirect->withErrors(['payment' => $error]);
        }

        return $redirect;
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
