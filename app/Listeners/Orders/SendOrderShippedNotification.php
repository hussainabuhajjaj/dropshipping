<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderShipped;
use App\Notifications\Orders\OrderShippedNotification;
use Illuminate\Support\Facades\Notification;

class SendOrderShippedNotification
{
    public function handle(OrderShipped $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;
        $locale = $order->notificationLocale();

        $notification = (new OrderShippedNotification(
            $order,
            $event->trackingNumber,
            $event->carrier,
            $event->trackingUrl
        ))->locale($locale);

        if ($notifiable) {
            Notification::send($notifiable, $notification);
        } else {
            // Send to guest email
            Notification::route('mail', $order->email)
                ->notify($notification);
        }
    }
}
