<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderDelivered;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use App\Notifications\Orders\DeliveryConfirmedNotification;
use Illuminate\Support\Facades\Notification;

class SendDeliveryConfirmedNotification
{
    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;
        $locale = $order->notificationLocale();
        $notification = (new DeliveryConfirmedNotification($order, $event->deliveredAt))->locale($locale);

        if ($notifiable) {
            Notification::send($notifiable, $notification);
            return;
        }

        Notification::route('mail', $order->email)->notify($notification);

        $this->notifyAdmins($order, $event->deliveredAt);
    }

    private function notifyAdmins($order, ?string $deliveredAt): void
    {
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $detail = $deliveredAt ? "Delivered at {$deliveredAt}" : null;

        Notification::send(
            $admins,
            new AdminOrderEventNotification($order, 'Order delivered', $detail)
        );
    }
}
