<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use Illuminate\Support\Facades\DB;
use App\Domain\Observability\EventLogger;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminFulfillmentIssue;
use App\Notifications\CustomerShipmentNotification;

class FulfillmentService
{
    public function __construct(
        private readonly FulfillmentSelector $selector,
        private readonly EventLogger $logger,
    ) {
    }

    public function dispatchOrderItem(OrderItem $orderItem): FulfillmentJob
    {
        $provider = $this->resolveProvider($orderItem);
        $strategy = $this->selector->resolveForOrderItem($orderItem);
        $requestData = new FulfillmentRequestData(
            orderItem: $orderItem,
            provider: $provider,
            supplierProduct: $orderItem->supplierProduct,
            shippingAddress: $orderItem->order?->shippingAddress,
            billingAddress: $orderItem->order?->billingAddress,
            options: ['currency' => $orderItem->order?->currency],
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
                $this->notifyAdminsIssue($orderItem, $result->rawResponse['error'] ?? 'Fulfillment failed');
            }

            return $job->refresh();
        });
    }

    private function resolveProvider(OrderItem $orderItem): FulfillmentProvider
    {
        $provider = $orderItem->fulfillmentProvider
            ?? $orderItem->supplierProduct?->fulfillmentProvider
            ?? $orderItem->productVariant?->product?->defaultFulfillmentProvider;

        if (! $provider) {
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

    private function recordShipment(OrderItem $orderItem, FulfillmentResult $result): void
    {
        Shipment::updateOrCreate(
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

    private function reconcileOrderShipping(\App\Domain\Orders\Models\Order $order): void
    {
        $actual = (float) ($order->shipments()->sum('postage_amount') ?? 0);
        $estimated = (float) ($order->shipping_total_estimated ?? $order->shipping_total ?? 0);
        $variance = round($actual - $estimated, 2);

        $order->update([
            'shipping_total_actual' => $actual,
            'shipping_variance' => $variance,
            'shipping_reconciled_at' => now(),
        ]);
    }

    private function notifyAdminsIssue(OrderItem $orderItem, string $message): void
    {
        $recipients = User::query()->whereIn('role', ['admin', 'staff'])->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AdminFulfillmentIssue($orderItem, $message));
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
