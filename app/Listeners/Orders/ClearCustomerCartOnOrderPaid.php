<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderPaid;
use App\Models\Cart;

class ClearCustomerCartOnOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        $customerId = $order->customer_id;
        if (! $customerId) {
            return;
        }

        $cart = Cart::query()->where('user_id', $customerId)->first();
        $cart?->emptyCart();
    }
}
