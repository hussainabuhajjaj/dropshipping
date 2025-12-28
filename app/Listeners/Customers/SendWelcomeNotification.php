<?php

declare(strict_types=1);

namespace App\Listeners\Customers;

use App\Events\Customers\CustomerRegistered;
use App\Notifications\Customers\WelcomeNotification;
use Illuminate\Support\Facades\Notification;

class SendWelcomeNotification
{
    public function handle(CustomerRegistered $event): void
    {
        $customer = $event->customer;
        
        Notification::send($customer, new WelcomeNotification($customer));
    }
}
