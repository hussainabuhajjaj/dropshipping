<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderCancellationRequested;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleOrderCancellation
{
    public function handle(OrderCancellationRequested $event): void
    {
        $order = $event->order;
        
        Log::info('Order cancellation requested', [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'reason' => $event->reason,
            'cancelled_by' => 'customer',
        ]);

        // Notify admins of the cancellation
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isNotEmpty()) {
            $reason = $event->reason ? "\nReason: {$event->reason}" : '';
            Notification::send(
                $admins,
                new AdminOrderEventNotification(
                    $order,
                    'Order cancelled by customer',
                    "Order #{$order->number} cancelled. Refund amount: {$order->currency} {$order->grand_total}{$reason}"
                )
            );
        }
    }
}
