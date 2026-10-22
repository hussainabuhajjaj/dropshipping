<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Fulfillment\Services\FulfillmentDispatchService;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(private readonly FulfillmentDispatchService $fulfillmentDispatchService)
    {
    }

    /**
     * Automatically dispatch fulfillment when order becomes paid.
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status') || $order->payment_status !== 'paid') {
            return;
        }

        $dispatched = $this->fulfillmentDispatchService->dispatchForOrder($order);

        Log::info('Order paid fulfillment dispatch processed.', [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'dispatched_items' => $dispatched,
        ]);
    }
}
