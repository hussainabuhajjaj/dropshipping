<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderPlaced;
use App\Models\User;
use App\Notifications\AdminOrderEventNotification;
use App\Notifications\Orders\OrderConfirmedNotification;
use App\Notifications\Orders\OrderPendingPaymentNotification;
use App\Notifications\Orders\PaymentReceiptNotification;
use Illuminate\Support\Facades\Notification;

class SendOrderConfirmedNotification
{
    public function handle(OrderPlaced|OrderPaid $event): void
    {
        $order = $event->order;
        $notifiable = $order->customer ?? $order->user;
        $locale = $order->notificationLocale();

        if ($order->payment_status !== 'paid') {
            $pending = (new OrderPendingPaymentNotification($order))->locale($locale);

            if ($notifiable) {
                Notification::send($notifiable, $pending);
            } else {
                Notification::route('mail', $order->email)
                    ->notify($pending);
            }

            return;
        }

        // Get the payment for receipt
        $payment = $order->payments()->where('status', 'completed')->latest()->first();

        if ($notifiable) {
            Notification::send($notifiable, (new OrderConfirmedNotification($order))->locale($locale));
            if ($payment) {
                Notification::send($notifiable, (new PaymentReceiptNotification($order, $payment))->locale($locale));
            }
        }

        if (! $notifiable) {
            Notification::route('mail', $order->email)
                ->notify((new OrderConfirmedNotification($order))->locale($locale));
            if ($payment) {
                Notification::route('mail', $order->email)
                    ->notify((new PaymentReceiptNotification($order, $payment))->locale($locale));
            }
        }

        $this->notifyAdmins($order);
    }

    private function notifyAdmins($order): void
    {
        $admins = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            new AdminOrderEventNotification($order, 'Order paid', 'Customer payment confirmed.')
        );
    }
}
