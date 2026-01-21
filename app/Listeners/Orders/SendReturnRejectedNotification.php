<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\ReturnRejected;
use App\Notifications\Orders\ReturnRejectedNotification;
use Illuminate\Support\Facades\Notification;

class SendReturnRejectedNotification
{
    public function handle(ReturnRejected $event): void
    {
        $returnRequest = $event->returnRequest;
        $customer = $returnRequest->customer;
        $locale = $returnRequest->order?->notificationLocale() ?? config('app.locale', 'en');

        if ($customer) {
            Notification::send(
                $customer,
                (new ReturnRejectedNotification($returnRequest, $event->rejectionReason))->locale($locale)
            );
        } else {
            // Send to order email if no customer account
            Notification::route('mail', $returnRequest->order->email)
                ->notify((new ReturnRejectedNotification($returnRequest, $event->rejectionReason))->locale($locale));
        }
    }
}
