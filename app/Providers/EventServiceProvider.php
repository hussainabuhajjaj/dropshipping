<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Orders\OrderPlaced;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderShipped;
use App\Events\Orders\OrderCancellationRequested;
use App\Events\Orders\FulfillmentDelayed;
use App\Events\Orders\CustomsUpdated;
use App\Events\Orders\OrderDelivered;
use App\Events\Orders\RefundProcessed;
use App\Events\Orders\ReturnApproved;
use App\Events\Orders\ReturnRejected;
use App\Events\Customers\CustomerRegistered;
use App\Listeners\Orders\SendOrderConfirmedNotification;
use App\Listeners\Orders\SendOrderShippedNotification;
use App\Listeners\Orders\SendShippingDelayNotification;
use App\Listeners\Orders\SendCustomsInfoNotification;
use App\Listeners\Orders\SendDeliveryConfirmedNotification;
use App\Listeners\Orders\SendRefundProcessedNotification;
use App\Listeners\Orders\SendReturnApprovedNotification;
use App\Listeners\Orders\SendReturnRejectedNotification;
use App\Listeners\Orders\HandleOrderCancellation;
use App\Listeners\Customers\SendWelcomeNotification;
use App\Listeners\Auth\LogAdminLogin;
use App\Domain\Products\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            LogAdminLogin::class,
        ],
        OrderPlaced::class => [
            SendOrderConfirmedNotification::class,
        ],
        OrderPaid::class => [
            SendOrderConfirmedNotification::class,
        ],
        OrderShipped::class => [
            SendOrderShippedNotification::class,
        ],
        FulfillmentDelayed::class => [
            SendShippingDelayNotification::class,
        ],
        CustomsUpdated::class => [
            SendCustomsInfoNotification::class,
        ],
        OrderDelivered::class => [
            SendDeliveryConfirmedNotification::class,
        ],
        OrderCancellationRequested::class => [
            HandleOrderCancellation::class,
        ],
        RefundProcessed::class => [
            SendRefundProcessedNotification::class,
        ],
        ReturnApproved::class => [
            SendReturnApprovedNotification::class,
        ],
        ReturnRejected::class => [
            SendReturnRejectedNotification::class,
        ],
        CustomerRegistered::class => [
            SendWelcomeNotification::class,
        ],
    ];

    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        \App\Domain\Orders\Models\Order::observe(\App\Observers\OrderObserver::class);
    }
}
