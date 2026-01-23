<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\RefundProcessed;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use App\Notifications\Orders\RefundProcessedNotification;
use Illuminate\Support\Facades\Notification;

class SendRefundProcessedNotification
{
    public function handle(RefundProcessed $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;
        $locale = $order->notificationLocale();
        $notification = (new RefundProcessedNotification(
            $order,
            $event->amount,
            $event->currency,
            $event->reason
        ))->locale($locale);

        if ($notifiable) {
            Notification::send($notifiable, $notification);
            return;
        }

        Notification::route('mail', $order->email)->notify($notification);

        $this->notifyAdmins($order, $event->amount, $event->currency, $event->reason);
    }

    private function notifyAdmins($order, float $amount, string $currency, ?string $reason): void
    {
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $detail = "Refund {$currency} {$amount}" . ($reason ? " · {$reason}" : '');

        Notification::send(
            $admins,
            new AdminOrderEventNotification($order, 'Refund processed', $detail)
        );
    }
}
