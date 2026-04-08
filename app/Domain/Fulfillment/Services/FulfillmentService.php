<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Notifications\CustomerShipmentOrderNotification;
use Illuminate\Support\Facades\DB;
use App\Domain\Observability\EventLogger;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminFulfillmentIssue;
use App\Notifications\CustomerShipmentNotification;

class FulfillmentService
{
    public function __construct(
        private readonly FulfillmentSelector $selector,
        private readonly EventLogger         $logger,
    )
    {
    }

    public function dispatchOrderItem(OrderItem $orderItem): FulfillmentJob
    {
        $this->assertOrderIsPaid($orderItem->order);

        $provider = $this->resolveProvider($orderItem);
        $strategy = $this->selector->resolveForOrderItem($orderItem);
        $requestData = new FulfillmentRequestData(

            order_id: $orderItem->order_id,
            order_items: [['id' => $orderItem->id]],
            orderItem: $orderItem,
            provider: $provider,
            supplierProduct: $orderItem->supplierProduct,
            shippingAddress: $orderItem->order?->shippingAddress,
            billingAddress: $orderItem->order?->billingAddress,
            options: [
                'currency' => $orderItem->order?->currency,
                'ship_to_country' => $orderItem->order?->shippingAddress?->country,
            ],
        );

        return DB::transaction(function () use ($orderItem, $provider, $strategy, $requestData) {
            $job = FulfillmentJob::create([
                'order_item_id' => $orderItem->id,
                'fulfillment_provider_id' => $provider->id,
                'payload' => $this->buildPayload($requestData),
                'status' => 'pending',
                'dispatched_at' => now(),
            ]);

            $result = $strategy->dispatch($requestData);

            $this->recordAttempt($job, $requestData, $result);
            $this->updateJobStatus($job, $result);
            $this->updateOrderItemStatus($orderItem, $result);

            $this->logger->fulfillment(
                $orderItem,
                'dispatch',
                $result->status,
                $result->rawResponse['error'] ?? null,
                $result->rawResponse
            );

            if ($result->trackingNumber || $result->trackingUrl) {
                $this->recordShipment($orderItem, $result);
                $this->notifyCustomerShipment($orderItem);
            }

            if ($result->failed()) {
                $firstItem = $product_items->first();
                if ($firstItem) {
                    $this->notifyAdminsIssue($firstItem, $result->rawResponse['error'] ?? 'Fulfillment failed');
                }
            }

            return $job->refresh();
        });
    }

    public function dispatchOrderV2(Order $order, $product_items, $provider): FulfillmentJob
    {
        $this->assertOrderIsPaid($order);

        $first_item = $product_items->first();
        $strategy = $this->selector->resolveForOrderItem($first_item);

        $requestData = new FulfillmentRequestData(
            order_id: $order->id,
            order_items: $product_items->toArray(),
            orderItem: null,
            provider: $provider,
            shippingAddress: $order?->shippingAddress,
            billingAddress: $order?->billingAddress,
            options: [
                'currency' => $order?->currency,
                'ship_to_country' => $order?->shippingAddress?->country,
            ],
        );
        return DB::transaction(function () use ($order, $product_items, $provider, $strategy, $requestData) {

            $job = FulfillmentJob::query()->create([
                'order_id' => $order->id,
                'fulfillment_provider_id' => $provider->id,
                'payload' => $this->buildPayloadForOrder($requestData),
                'status' => 'pending',
                'dispatched_at' => now(),
            ]);


            $result = $strategy->dispatch($requestData);
            Log::info('result : ' . json_encode($result));
            $this->recordOrderAttempt($job, $requestData, $result);
            $this->updateJobStatus($job, $result);
            $this->updateOrderItemsStatus($product_items, $result);

            $fulfillment_status = $product_items->first()?->fresh()?->fulfillment_status;
            foreach ($product_items as $orderItem) {
                $this->logger->fulfillment(
                    $orderItem,
                    'dispatch',
                    $result->status,
                    $result->rawResponse['error'] ?? null,
                    $result->rawResponse
                );
            }

            if ($result->trackingNumber || $result->trackingUrl) {
                $shipment = $this->recordShipmentForOrder($order, $product_items, $result);
                $customer = $order->customer;
                $this->notifyCustomerShipmentOrder($customer, $shipment, $fulfillment_status);
            }

            if ($result->failed()) {
                $this->notifyAdminsIssue($orderItem, $result->rawResponse['error'] ?? 'Fulfillment failed');
            }

            return $job->refresh();
        });
    }



    private function assertOrderIsPaid(?Order $order): void
    {
        if ($order?->payment_status !== 'paid') {
            throw new FulfillmentException('Cannot dispatch fulfillment before payment is confirmed.');
        }
    }

    private function resolveProvider(OrderItem $orderItem): FulfillmentProvider
    {
        $provider = $orderItem->fulfillmentProvider
            ?? $orderItem->supplierProduct?->fulfillmentProvider
            ?? $orderItem->productVariant?->product?->defaultFulfillmentProvider;

        if (!$provider) {
            throw new FulfillmentException('Missing fulfillment provider for order item.');
        }

        return $provider;
    }

    private function buildPayload(FulfillmentRequestData $requestData): array
    {
        return [
            'order_item_id' => $requestData->orderItem->id,
            'provider_code' => $requestData->provider->code,
            'supplier_product_id' => $requestData->supplierProduct?->id,
            'shipping_address' => $requestData->shippingAddress?->toArray(),
            'billing_address' => $requestData->billingAddress?->toArray(),
            'options' => $requestData->options,
        ];
    }

    private function buildPayloadForOrder(FulfillmentRequestData $requestData): array
    {
        return [
            'order_id' => $requestData->order_id,
            'order_item_ids' => collect($requestData->order_items ?? [])->pluck('id')->map(static fn ($id) => (int) $id)->values()->all(),
            'provider_code' => $requestData->provider->code,
            'supplier_product_id' => $requestData->supplierProduct?->id,
            'shipping_address' => $requestData->shippingAddress?->toArray(),
            'billing_address' => $requestData->billingAddress?->toArray(),
            'options' => $requestData->options,
        ];
    }

    private function recordAttempt(FulfillmentJob $job, FulfillmentRequestData $data, FulfillmentResult $result): void
    {
        $job->attempts()->create([
            'attempt_no' => $job->attempts()->count() + 1,
            'request_payload' => $this->buildPayload($data),
            'response_payload' => $result->rawResponse,
            'status' => $result->succeeded() ? 'success' : 'failed',
            'error_message' => $result->failed() ? ($result->rawResponse['error'] ?? null) : null,
        ]);
    }

    private function recordOrderAttempt(FulfillmentJob $job, FulfillmentRequestData $data, FulfillmentResult $result): void
    {
        $job->attempts()->create([
            'attempt_no' => $job->attempts()->count() + 1,
            'request_payload' => $this->buildPayloadForOrder($data),
            'response_payload' => $result->rawResponse,
            'status' => $result->succeeded() ? 'success' : 'failed',
            'error_message' => $result->failed() ? ($result->rawResponse['error'] ?? null) : null,
        ]);
    }

    private function updateJobStatus(FulfillmentJob $job, FulfillmentResult $result): void
    {
        $job->status = $result->status;
        $job->external_reference = $result->externalReference;
        $job->fulfilled_at = $result->succeeded() ? now() : null;
        $job->last_error = $result->failed() ? ($result->rawResponse['error'] ?? null) : null;
        $job->save();
    }

    private function updateOrderItemStatus(OrderItem $orderItem, FulfillmentResult $result): void
    {
        $orderItem->fulfillment_status = $result->status === 'succeeded' ? 'fulfilled' : $result->status;
        $orderItem->save();
    }

    private function updateOrderItemsStatus($product_items, FulfillmentResult $result): void
    {
        foreach ($product_items as $product_item) {
            $product_item->update(['fulfillment_status' => ($result->status === 'succeeded' ? 'fulfilled' : $result->status)]);
        }
    }

    private function recordShipment(OrderItem $orderItem, FulfillmentResult $result): void
    {
        Shipment::query()->updateOrCreate(
            ['order_item_id' => $orderItem->id, 'tracking_number' => $result->trackingNumber],
            [
                'carrier' => $orderItem->meta['carrier'] ?? null,
                'tracking_url' => $result->trackingUrl,
                'logistic_name' => $result->logisticName,
                'cj_order_id' => $result->cjOrderId,
                'shipment_order_id' => $result->shipmentOrderId,
                'postage_amount' => $result->postageAmount,
                'currency' => $result->currency ?? $orderItem->order?->currency,
                'shipped_at' => now(),
                'raw_events' => $result->rawResponse['events'] ?? null,
            ]
        );

        if ($orderItem->order) {
            $this->reconcileOrderShipping($orderItem->order);

            // Queue CJ payment after shipment recorded
            if ($orderItem->order->cj_order_id) {
                \App\Jobs\PayCJBalanceJob::dispatch($orderItem->order->id);
            }
        }
    }

    private function recordShipmentForOrder(Order $order, $order_items, FulfillmentResult $result)
    {
        $primaryOrderItem = $order_items->first();

        $shipment = Shipment::query()->updateOrCreate(
            ['order_id' => $order->id, 'tracking_number' => $result->trackingNumber],
            [
                'carrier' => $primaryOrderItem?->meta['carrier'] ?? null,
                'tracking_url' => $result->trackingUrl,
                'logistic_name' => $result->logisticName,
                'cj_order_id' => $result->cjOrderId,
                'shipment_order_id' => $result->shipmentOrderId,
                'postage_amount' => $result->postageAmount,
                'currency' => $result->currency ?? $order->currency,
                'shipped_at' => now(),
                'raw_events' => $result->rawResponse['events'] ?? null,
            ]
        );

        $shipment->items()->delete();
        foreach ($order_items as $order_item) {
            $shipment->items()->create(['order_item_id' => $order_item->id]);
        }

        if ($order) {
            $this->reconcileOrderShipping($order);

            // Queue CJ payment after shipment recorded
            if ($order->cj_order_id) {
                \App\Jobs\PayCJBalanceJob::dispatch($order->id);
            }
        }

        return $shipment;
    }

    private function reconcileOrderShipping(Order $order): void
    {
        $actual = (float)($order->shipments()->sum('postage_amount') ?? 0);
        $estimated = (float)($order->shipping_total_estimated ?? $order->shipping_total ?? 0);
        $variance = round($actual - $estimated, 2);

        $order->update([
            'shipping_total_actual' => $actual,
            'shipping_variance' => $variance,
            'shipping_reconciled_at' => now(),
        ]);

        app(\App\Domain\Orders\Services\OrderCostBreakdownService::class)->recalculate($order);
    }

    private function notifyAdminsIssue(OrderItem $orderItem, string $message): void
    {
        $recipients = User::query()->whereIn('role', ['admin', 'staff'])->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AdminFulfillmentIssue($orderItem, $message));
        }
    }

    private function notifyCustomerShipmentOrder($customer, $shipment, $fulfillment_status): void
    {
        if ($customer) {
            $customer->notify(new CustomerShipmentOrderNotification($shipment, $fulfillment_status));
        }
    }

    private function notifyCustomerShipment(OrderItem $orderItem): void
    {
        $customer = $orderItem->order?->customer;
        if ($customer) {
            $customer->notify(new CustomerShipmentNotification($orderItem));
        }
    }
}
