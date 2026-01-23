<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\CustomsUpdated;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use App\Notifications\Orders\CustomsInfoNotification;
use Illuminate\Support\Facades\Notification;

class SendCustomsInfoNotification
{
    public function handle(CustomsUpdated $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;
        $locale = $order->notificationLocale();
        $notification = (new CustomsInfoNotification($order, $event->note))->locale($locale);

        if ($notifiable) {
            Notification::send($notifiable, $notification);
            return;
        }

        Notification::route('mail', $order->email)->notify($notification);

        $this->notifyAdmins($order, $event->note);
    }

    private function notifyAdmins($order, ?string $note): void
    {
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            new AdminOrderEventNotification($order, 'Customs update', $note)
        );
    }
}
