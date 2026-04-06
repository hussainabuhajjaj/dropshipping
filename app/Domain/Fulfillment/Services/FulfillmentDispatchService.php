<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Jobs\DispatchFulfillmentJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FulfillmentDispatchService
{
    /**
     * Dispatch eligible order items and mark order as fulfilling when needed.
     */
    public function dispatchForOrder(Order $order): int
    {
        if (! $this->orderIsPaid($order)) {
            Log::info('Skipping fulfillment dispatch for unpaid order.', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'payment_status' => $order->payment_status,
            ]);

            return 0;
        }

        $order->loadMissing([
            'orderItems.fulfillmentProvider',
            'orderItems.supplierProduct.fulfillmentProvider',
            'orderItems.productVariant.product.defaultFulfillmentProvider',
        ]);

        $dispatched = 0;

        $groups = $order->orderItems
            ->map(fn (OrderItem $item) => ['item' => $item, 'provider_id' => $this->resolveProviderId($item)])
            ->filter(fn (array $row) => $row['provider_id'] !== null)
            ->groupBy('provider_id');

        foreach ($groups as $providerId => $rows) {
            $itemIds = collect($rows)->pluck('item.id')->values()->all();
            $claimedItemIds = $this->claimGroupForDispatch($order, $itemIds, (int) $providerId);

            if ($claimedItemIds === []) {
                continue;
            }

            try {
                DispatchFulfillmentJob::dispatch(
                    orderId: $order->id,
                    providerId: (int) $providerId,
                    orderItemIds: $claimedItemIds,
                );
                $dispatched += count($claimedItemIds);
            } catch (\Throwable $e) {
                OrderItem::query()
                    ->whereIn('id', $claimedItemIds)
                    ->where('fulfillment_status', 'fulfilling')
                    ->update(['fulfillment_status' => 'pending']);

                Log::error('Failed to dispatch fulfillment job.', [
                    'order_id' => $order->id,
                    'provider_id' => (int) $providerId,
                    'order_item_ids' => $claimedItemIds,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($dispatched > 0 && ! in_array($order->status, ['fulfilled', 'cancelled', 'refunded'], true)) {
            $order->update(['status' => 'fulfilling']);
        }

        return $dispatched;
    }

    /**
     * Dispatch a single eligible order item.
     */
    public function dispatchForOrderItem(OrderItem $item): bool
    {
        $item->loadMissing('order');

        if (! $item->order || ! $this->orderIsPaid($item->order)) {
            Log::info('Skipping fulfillment dispatch for unpaid order item.', [
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'payment_status' => $item->order?->payment_status,
            ]);

            return false;
        }

        if (! $this->claimForDispatch($item)) {
            return false;
        }

        try {
            DispatchFulfillmentJob::dispatch($item->id);
            return true;
        } catch (\Throwable $e) {
            OrderItem::query()
                ->whereKey($item->id)
                ->where('fulfillment_status', 'fulfilling')
                ->update(['fulfillment_status' => 'pending']);

            Log::error('Failed to dispatch fulfillment job.', [
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function claimForDispatch(OrderItem $item): bool
    {
        return DB::transaction(function () use ($item): bool {
            /** @var OrderItem|null $fresh */
            $fresh = OrderItem::query()
                ->lockForUpdate()
                ->with([
                    'fulfillmentProvider',
                    'supplierProduct.fulfillmentProvider',
                    'productVariant.product.defaultFulfillmentProvider',
                ])
                ->find($item->id);

            if (! $fresh) {
                return false;
            }

            if (! $fresh->order || ! $this->orderIsPaid($fresh->order)) {
                return false;
            }

            if (in_array($fresh->fulfillment_status, ['fulfilling', 'fulfilled', 'failed', 'cancelled'], true)) {
                return false;
            }

            $hasRunningJob = FulfillmentJob::query()
                ->where('order_item_id', $fresh->id)
                ->whereNotIn('status', ['failed', 'cancelled', 'succeeded'])
                ->exists();

            if ($hasRunningJob) {
                return false;
            }

            $hasProvider = $fresh->fulfillmentProvider
                || $fresh->supplierProduct?->fulfillmentProvider
                || $fresh->productVariant?->product?->defaultFulfillmentProvider;

            if (! $hasProvider) {
                Log::warning('Skipping fulfillment dispatch; no provider resolved.', [
                    'order_id' => $fresh->order_id,
                    'order_item_id' => $fresh->id,
                ]);

                return false;
            }

            $fresh->update(['fulfillment_status' => 'fulfilling']);

            return true;
        });
    }

    private function claimGroupForDispatch(Order $order, array $itemIds, int $providerId): array
    {
        return DB::transaction(function () use ($order, $itemIds, $providerId): array {
            /** @var Collection<int, OrderItem> $items */
            $items = OrderItem::query()
                ->lockForUpdate()
                ->with([
                    'order',
                    'fulfillmentProvider',
                    'supplierProduct.fulfillmentProvider',
                    'productVariant.product.defaultFulfillmentProvider',
                ])
                ->where('order_id', $order->id)
                ->whereIn('id', $itemIds)
                ->get();

            if ($items->isEmpty()) {
                return [];
            }

            $claimableIds = [];

            if (! $this->orderIsPaid($order)) {
                return [];
            }

            foreach ($items as $item) {
                if ($this->resolveProviderId($item) !== $providerId) {
                    continue;
                }

                if (! $item->order || ! $this->orderIsPaid($item->order)) {
                    continue;
                }

                if (in_array($item->fulfillment_status, ['fulfilling', 'fulfilled', 'failed', 'cancelled'], true)) {
                    continue;
                }

                $hasRunningJob = FulfillmentJob::query()
                    ->where('order_item_id', $item->id)
                    ->whereNotIn('status', ['failed', 'cancelled', 'succeeded'])
                    ->exists();

                if ($hasRunningJob) {
                    continue;
                }

                $claimableIds[] = $item->id;
            }

            if ($claimableIds !== []) {
                OrderItem::query()
                    ->whereIn('id', $claimableIds)
                    ->update(['fulfillment_status' => 'fulfilling']);
            }

            return $claimableIds;
        });
    }

    private function resolveProviderId(OrderItem $item): ?int
    {
        return $item->fulfillmentProvider?->id
            ?? $item->supplierProduct?->fulfillmentProvider?->id
            ?? $item->productVariant?->product?->defaultFulfillmentProvider?->id;
    }

    private function orderIsPaid(?Order $order): bool
    {
        return $order?->payment_status === 'paid';
    }
}

