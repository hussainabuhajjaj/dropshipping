<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Orders\Models\Order;
use App\Domain\Fulfillment\Services\CJPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use Throwable;

class PayCJBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(CJPaymentService $paymentService): void
    {
        $order = Order::findOrFail($this->orderId);

        try {
            Log::info('Processing CJ balance payment', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'attempt' => $this->attempts(),
            ]);

            $paymentService->payOrder($order);

            Log::info('CJ payment job completed', [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]);

        } catch (Throwable $e) {
            Log::error('CJ payment job failed', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $e->getMessage(),
            ]);

            // Notify admins on final failure
            if ($this->attempts() >= $this->tries) {
                $this->notifyAdminsPaymentFailed($order, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Notify admins when final payment attempt fails.
     */
    private function notifyAdminsPaymentFailed(Order $order, string $error): void
    {
        $admins = User::whereIn('role', ['admin', 'staff'])->get();

        if ($admins->isEmpty()) {
            Log::warning('No admins found to notify of CJ payment failure');
            return;
        }

        Notification::send(
            $admins,
            new \App\Notifications\CJPaymentFailedNotification($order, $error)
        );
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::find($this->orderId);
        if ($order) {
            Log::error('CJ payment job permanently failed', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
