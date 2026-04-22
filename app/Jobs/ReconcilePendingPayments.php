<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcilePendingPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Execute the job.
     */
    public function handle(PaystackService $paystackService): void
    {
        Log::info('Starting payment reconciliation job');

        // Get pending payments older than 5 minutes to avoid race conditions
        $pendingPayments = Payment::where('provider', 'paystack')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->with('order')
            ->get();

        Log::info('Found pending payments for reconciliation', [
            'count' => $pendingPayments->count(),
        ]);

        foreach ($pendingPayments as $payment) {
            try {
                $this->reconcilePayment($payment, $paystackService);
            } catch (\Throwable $e) {
                Log::error('Failed to reconcile payment', [
                    'payment_id' => $payment->id,
                    'reference' => $payment->provider_reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Payment reconciliation job completed');
    }

    private function reconcilePayment(Payment $payment, PaystackService $paystackService): void
    {
        Log::info('Reconciling payment', [
            'payment_id' => $payment->id,
            'reference' => $payment->provider_reference,
            'order_id' => $payment->order_id,
            'created_at' => $payment->created_at,
        ]);

        if (!$payment->provider_reference) {
            Log::warning('Payment missing provider reference', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $verification = $paystackService->verifyTransaction($payment->provider_reference);

        Log::info('Payment verification result', [
            'payment_id' => $payment->id,
            'reference' => $payment->provider_reference,
            'verification_status' => $verification['status'],
            'verification_amount' => $verification['amount'],
            'verification_currency' => $verification['currency'],
        ]);

        if ($verification['status'] === 'success') {
            // Verify amounts match (convert from cents)
            $expectedAmount = $payment->amount * 100;
            $actualAmount = $verification['amount'];

            if ($expectedAmount !== $actualAmount) {
                Log::warning('Payment amount mismatch', [
                    'payment_id' => $payment->id,
                    'reference' => $payment->provider_reference,
                    'expected' => $expectedAmount,
                    'actual' => $actualAmount,
                ]);
                return;
            }

            // Update payment status
            $payment->update(['status' => 'paid']);

            // Update order status
            $order = $payment->order;
            if ($order) {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing',
                ]);

                Log::info('Order updated after reconciliation', [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'payment_id' => $payment->id,
                ]);
            }

            Log::info('Payment successfully reconciled', [
                'payment_id' => $payment->id,
                'reference' => $payment->provider_reference,
                'order_number' => $order?->number,
            ]);
        } elseif ($verification['status'] === 'failed') {
            // Mark failed payments
            $payment->update(['status' => 'failed']);

            $order = $payment->order;
            if ($order) {
                $order->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                ]);
            }

            Log::info('Payment marked as failed after reconciliation', [
                'payment_id' => $payment->id,
                'reference' => $payment->provider_reference,
                'order_number' => $order?->number,
            ]);
        } else {
            Log::info('Payment still pending in Paystack', [
                'payment_id' => $payment->id,
                'reference' => $payment->provider_reference,
                'paystack_status' => $verification['status'],
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment reconciliation job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
