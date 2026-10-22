<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchFulfillmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int|array $backoff = 60;

    public function __construct(
        public ?int $orderItemId = null,
        public ?int $orderId = null,
        public ?int $providerId = null,
        public array $orderItemIds = [],
    ) {
        $this->onQueue('fulfillment');
    }

    public function handle(FulfillmentService $fulfillmentService): void
    {
        if ($this->orderId && $this->providerId) {
            $order = Order::with(['shippingAddress', 'billingAddress', 'customer'])->findOrFail($this->orderId);
            $provider = FulfillmentProvider::findOrFail($this->providerId);

            $itemsQuery = OrderItem::with([
                'order.shippingAddress',
                'order.billingAddress',
                'productVariant.product.defaultFulfillmentProvider',
                'fulfillmentProvider',
                'supplierProduct.fulfillmentProvider',
            ])->where('order_id', $this->orderId);

            if (! empty($this->orderItemIds)) {
                $itemsQuery->whereIn('id', $this->orderItemIds);
            }

            $items = $itemsQuery->get();

            if ($items->isEmpty()) {
                return;
            }

            $providerRetryLimit = $provider->retry_limit ?? $this->tries;

            if ($this->attempts() > $providerRetryLimit) {
                $this->fail(new \RuntimeException('Exceeded fulfillment retry limit for provider.'));
                return;
            }

            $fulfillmentService->dispatchOrderV2($order, $items, $provider);

            return;
        }

        if (! $this->orderItemId) {
            return;
        }

        $orderItem = OrderItem::with([
            'order.shippingAddress',
            'order.billingAddress',
            'productVariant.product.defaultFulfillmentProvider',
            'fulfillmentProvider',
            'supplierProduct.fulfillmentProvider',
        ])->findOrFail($this->orderItemId);

        $providerRetryLimit = $orderItem->fulfillmentProvider?->retry_limit ?? $this->tries;

        if ($this->attempts() > $providerRetryLimit) {
            $this->fail(new \RuntimeException('Exceeded fulfillment retry limit for provider.'));
            return;
        }

        $fulfillmentService->dispatchOrderItem($orderItem);
    }
}
