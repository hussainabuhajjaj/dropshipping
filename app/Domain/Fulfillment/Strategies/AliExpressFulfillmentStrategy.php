<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Strategies;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Products\Services\AliExpressProductImportService;
use App\Infrastructure\Fulfillment\Clients\AliExpressClient;
use Illuminate\Support\Collection;

class AliExpressFulfillmentStrategy implements FulfillmentStrategy
{
    public function __construct(
        private readonly FulfillmentProvider $provider,
        private readonly AliExpressClient $client,
        private readonly AliExpressProductImportService $productImportService,
    ) {
    }

    public function dispatch(FulfillmentRequestData $request): FulfillmentResult
    {
        if ($stockFailure = $this->validateLiveStock($request)) {
            return $stockFailure;
        }

        try {
            $response = $this->client->createOrder($request);
        } catch (FulfillmentException $e) {
            return new FulfillmentResult(
                status: 'failed',
                rawResponse: ['error' => $e->getMessage()]
            );
        }

        $status = ($response['code'] ?? null) === '0' ? 'in_progress' : 'failed';
        $externalReference = (string) (
            data_get($response, 'result.order_id')
            ?? data_get($response, 'result.orderId')
            ?? data_get($response, 'result.trade_order_id')
            ?? data_get($response, 'result.tradeOrderId')
            ?? ''
        );

        return new FulfillmentResult(
            status: $status,
            externalReference: $externalReference !== '' ? $externalReference : null,
            rawResponse: $response ?? [],
        );
    }

    private function validateLiveStock(FulfillmentRequestData $request): ?FulfillmentResult
    {
        $shipToCountry = strtoupper(trim((string) ($request->options['ship_to_country'] ?? $request->shippingAddress?->country ?? 'CN')));

        foreach ($this->resolveOrderItems($request) as $orderItem) {
            $variant = $orderItem->productVariant;
            if (! $variant) {
                continue;
            }

            $liveStock = $this->productImportService->refreshVariantLiveStock($variant, [
                'ship_to_country' => $shipToCountry !== '' ? $shipToCountry : 'CN',
            ]);

            if ($liveStock === null || $liveStock >= (int) $orderItem->quantity) {
                continue;
            }

            $title = $orderItem->snapshot['variant'] ?? $variant->title ?? ('Variant #' . $variant->id);
            $error = $liveStock > 0
                ? "AliExpress stock changed for {$title}. Requested {$orderItem->quantity}, available {$liveStock}."
                : "AliExpress stock changed for {$title}. This variant is now out of stock.";

            return new FulfillmentResult(
                status: 'failed',
                rawResponse: [
                    'error' => $error,
                    'ali_live_stock' => $liveStock,
                    'order_item_id' => $orderItem->id,
                    'product_variant_id' => $variant->id,
                ],
            );
        }

        return null;
    }

    private function resolveOrderItems(FulfillmentRequestData $request): Collection
    {
        if ($request->orderItem instanceof OrderItem) {
            return collect([$request->orderItem->loadMissing('productVariant.product')]);
        }

        $ids = collect($request->order_items ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->with(['productVariant.product'])
            ->whereIn('id', $ids->all())
            ->get();
    }
}
