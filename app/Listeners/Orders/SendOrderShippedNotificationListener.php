<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderShipped;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use App\Notifications\Orders\OrderShippedNotification;
use Illuminate\Support\Facades\Notification;

class SendOrderShippedNotificationListener
{
    public function handle(OrderShipped $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;

        $notification = new OrderShippedNotification($order, $event->trackingNumber);

        if ($notifiable) {
            Notification::send($notifiable, $notification);
        }

        if (! $notifiable) {
            Notification::route('mail', $order->email)
                ->notify($notification);
        }

        // Notify admins
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                new AdminOrderEventNotification($order, 'Order shipped', "Tracking #: {$event->trackingNumber}")
            );
        }
    }
}
