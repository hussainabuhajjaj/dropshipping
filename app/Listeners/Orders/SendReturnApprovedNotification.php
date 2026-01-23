<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\ReturnApproved;
use App\Notifications\Orders\ReturnApprovedNotification;
use Illuminate\Support\Facades\Notification;

class SendReturnApprovedNotification
{
    public function handle(ReturnApproved $event): void
    {
        $returnRequest = $event->returnRequest;
        $customer = $returnRequest->customer;
        $locale = $returnRequest->order?->notificationLocale() ?? config('app.locale', 'en');

        if ($customer) {
            Notification::send(
                $customer,
                (new ReturnApprovedNotification($returnRequest, $event->returnLabelUrl))->locale($locale)
            );
        } else {
            // Send to order email if no customer account
            Notification::route('mail', $returnRequest->order->email)
                ->notify((new ReturnApprovedNotification($returnRequest, $event->returnLabelUrl))->locale($locale));
        }
    }
}
