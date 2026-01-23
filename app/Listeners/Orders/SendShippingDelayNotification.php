<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\FulfillmentDelayed;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use App\Notifications\Orders\ShippingDelayNotification;
use Illuminate\Support\Facades\Notification;

class SendShippingDelayNotification
{
    public function handle(FulfillmentDelayed $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;
        $locale = $order->notificationLocale();

        $notification = (new ShippingDelayNotification($order, $event->eta, $event->reason))->locale($locale);

        if ($notifiable) {
            Notification::send($notifiable, $notification);
            return;
        }

        Notification::route('mail', $order->email)->notify($notification);

        $this->notifyAdmins($order, $event->eta, $event->reason);
    }

    private function notifyAdmins($order, ?string $eta, ?string $reason): void
    {
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $detail = trim(implode(' ', array_filter([
            $reason ? "Reason: {$reason}" : null,
            $eta ? "ETA: {$eta}" : null,
        ])));

        Notification::send(
            $admins,
            new AdminOrderEventNotification($order, 'Shipping delay', $detail !== '' ? $detail : null)
        );
    }
}
