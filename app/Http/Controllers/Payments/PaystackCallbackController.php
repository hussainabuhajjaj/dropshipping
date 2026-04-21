<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\PaymentService;
use App\Models\Payment;
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

        $payment = Payment::query()
            ->where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->with('order')
            ->latest('id')
            ->first();

        if (! $payment) {
            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'Payment not found.']);
        }

        // Already paid (idempotent safe)
        if ($payment->status === 'paid' && $payment->order) {
            return redirect()
                ->route('orders.confirmation', [
                    'number' => $payment->order->number
                ])
                ->with('status', 'Payment confirmed.');
        }

        try {
            $payment = $paymentService
                ->verifyPaystack($reference)
                ->load('order');

        } catch (\Throwable $e) {
            Log::error('Paystack callback verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('orders.confirmation', [
                    'number' => $payment->order?->number ?? ''
                ])
                ->withErrors(['payment' => 'Verification failed.']);
        }

        if ($payment->status === 'paid' && $payment->order) {
            return redirect()
                ->route('orders.confirmation', [
                    'number' => $payment->order->number
                ])
                ->with('status', 'Payment confirmed.');
        }

        return redirect()
            ->route('orders.confirmation', [
                'number' => $payment->order?->number ?? ''
            ])
            ->withErrors(['payment' => 'Payment not completed.']);
    }
}