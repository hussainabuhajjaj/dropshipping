<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Orders\Models\Order;
use App\Jobs\DispatchFulfillmentJob;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     * 
     * Automatically dispatch fulfillment when order becomes paid.
     */
    public function updated(Order $order): void
    {
        Log::debug('OrderObserver.updated() called', [
            'order_id' => $order->id,
            'wasChanged' => $order->wasChanged('payment_status'),
            'payment_status' => $order->payment_status,
        ]);
        
        // Check if payment_status changed to 'paid'
        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            Log::info('✓ Payment status changed to paid - triggering fulfillment dispatch', [
                'order_id' => $order->id,
            ]);
            $this->dispatchFulfillmentForOrder($order);
        }
    }

    /**
     * Dispatch fulfillment jobs for all eligible order items.
     */
    private function dispatchFulfillmentForOrder(Order $order): void
    {
        // Load order items with fulfillment provider and supplier product
        $order->load('orderItems.fulfillmentProvider', 'orderItems.supplierProduct');

        $dispatched = 0;
        $skipped = 0;

        Log::debug('Checking order items for fulfillment dispatch', [
            'order_id' => $order->id,
            'item_count' => $order->orderItems->count(),
        ]);

        foreach ($order->orderItems as $orderItem) {
            Log::debug('Checking order item eligibility', [
                'item_id' => $orderItem->id,
                'has_provider' => $orderItem->fulfillmentProvider ? true : false,
                'fulfillment_status' => $orderItem->fulfillment_status,
            ]);

            // Only dispatch if:
            // 1. Item has a fulfillment provider assigned
            // 2. Item is not already fulfilling/fulfilled
            // 3. Item doesn't already have a successful fulfillment job
            $hasSuccessfulJob = $orderItem->fulfillmentJob && $orderItem->fulfillmentJob->status === 'succeeded';
            
            if (
                $orderItem->fulfillmentProvider &&
                !in_array($orderItem->fulfillment_status, ['fulfilling', 'fulfilled']) &&
                !$hasSuccessfulJob
            ) {
                try {
                    Log::info('Dispatching fulfillment job', ['item_id' => $orderItem->id]);
                    DispatchFulfillmentJob::dispatch($orderItem->id);
                    $dispatched++;

                    Log::info('Auto-dispatched fulfillment for order item', [
                        'order_id' => $order->id,
                        'order_number' => $order->number,
                        'order_item_id' => $orderItem->id,
                        'fulfillment_provider_id' => $orderItem->fulfillment_provider_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to auto-dispatch fulfillment for order item', [
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $skipped++;
                }
            } else {
                Log::debug('Item skipped (eligibility check failed)', [
                    'item_id' => $orderItem->id,
                    'has_provider' => $orderItem->fulfillmentProvider ? true : false,
                    'in_excluded_status' => in_array($orderItem->fulfillment_status, ['fulfilling', 'fulfilled']),
                    'has_successful_job' => $hasSuccessfulJob,
                ]);
                $skipped++;
            }
        }

        if ($dispatched > 0) {
            Log::info('Auto-dispatched fulfillment jobs for order', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'dispatched' => $dispatched,
                'skipped' => $skipped,
            ]);
        }
    }
}
