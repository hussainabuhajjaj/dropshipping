<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Fulfillment\Services\FulfillmentService;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchOrderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int|array $backoff = 60;
    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(FulfillmentService $fulfillmentService): void
    {

        $order = $this->order;

        $items = $order->orderItems()
            ->with(['productVariant'])
            ->where('fulfillment_status', '!=', 'fulfilling')
            ->get();

        $providers_in_order = $items->pluck('fulfillment_provider_id')->toArray();
        $providers = FulfillmentProvider::query()->whereIn('id', $providers_in_order)->get();

        foreach ($providers as $provider) {
            if ($provider->code == "cj") {
                $cj_provider = $provider;
                $cj_items = $items->where('fulfillment_provider_id', $provider->id);
                $fulfillmentService->dispatchCjOrder($order, $cj_items, $cj_provider);
            }
        }


//        dd($ite/**/ms);
//        $products = [];
//
//        foreach ($items as $item) {
//            $productVariant = $item->productVariant;
//
//            $products[] = [
//                "vid" => $productVariant->cj_vid,
//                "quantity" => $item->quantity,
//            ];
//
//        }
//
//        dd($products);
//        dd($this->order);
//        $orderItem = OrderItem::with([
//            'order.shippingAddress',
//            'order.billingAddress',
//            'productVariant.product.defaultFulfillmentProvider',
//            'fulfillmentProvider',
//            'supplierProduct.fulfillmentProvider',
//        ])->findOrFail($this->orderItemId);
//
//        $providerRetryLimit = $orderItem->fulfillmentProvider?->retry_limit ?? $this->tries;
//
//        if ($this->attempts() > $providerRetryLimit) {
//            $this->fail(new \RuntimeException('Exceeded fulfillment retry limit for provider.'));
//            return;
//        }

    }

}
