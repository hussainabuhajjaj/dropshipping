<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Payments\Paystack\PaystackService;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryPaystackCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;
    public int $backoff = [30, 120, 300]; // 30s, 2min, 5min

    public function __construct(
        private readonly string $reference
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaystackService $paystackService): void
    {
        Log::info('Retrying Paystack callback', [
            'reference' => $this->reference,
            'attempt' => $this->attempts(),
        ]);

        $payment = Payment::where('provider_reference', $this->reference)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            Log::warning('Payment not found for retry', [
                'reference' => $this->reference,
            ]);
            return;
        }

        try {
            $verification = $paystackService->verifyTransaction($this->reference);

            Log::info('Paystack retry verification result', [
                'reference' => $this->reference,
                'verification_data' => $verification,
            ]);

            if ($verification['status'] === 'success') {
                // Verify amounts match
                $expectedAmount = $payment->amount * 100;
                $actualAmount = $verification['amount'];

                if ($expectedAmount !== $actualAmount) {
                    Log::warning('Payment amount mismatch during retry', [
                        'reference' => $this->reference,
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

                    Log::info('Order updated after callback retry', [
                        'order_id' => $order->id,
                        'order_number' => $order->number,
                        'payment_id' => $payment->id,
                    ]);
                }

                Log::info('Payment successfully updated via retry', [
                    'payment_id' => $payment->id,
                    'reference' => $this->reference,
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

                Log::info('Payment marked as failed via retry', [
                    'payment_id' => $payment->id,
                    'reference' => $this->reference,
                    'order_number' => $order?->number,
                ]);
            } else {
                Log::info('Payment still pending during retry', [
                    'reference' => $this->reference,
                    'paystack_status' => $verification['status'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Paystack callback retry failed', [
                'reference' => $this->reference,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                Log::error('Paystack callback retry exhausted', [
                    'reference' => $this->reference,
                    'max_attempts' => $this->tries,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Paystack callback retry job failed permanently', [
            'reference' => $this->reference,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
