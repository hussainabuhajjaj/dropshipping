<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Strategies;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\LocalWareHouse;
use App\Services\Api\ApiException;
use App\Services\Api\ApiResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class CJDropshippingFulfillmentStrategy implements FulfillmentStrategy
{
    public function __construct(private readonly CJDropshippingClient $client)
    {
    }

    public function dispatch(FulfillmentRequestData $data): FulfillmentResult
    {
        $order = Order::query()
            ->with(['orderShippings', 'shippingAddress'])
            ->find($data->order_id);

        if (! $order) {
            throw new FulfillmentException("Order {$data->order_id} not found");
        }

        $providerSettings = $data->provider->settings ?? [];

        $order_items = collect($data->order_items ?? [])->pluck('id')->toArray();
        $new_order_items = OrderItem::query()
            ->whereIn('id', $order_items)
            ->with(['productVariant'])
            ->where('order_id', $data->order_id)
            ->get();

        if ($new_order_items->isEmpty()) {
            throw new FulfillmentException('CJ dispatch requires at least one order item.');
        }

        $products = $new_order_items
            ->map(function (OrderItem $item) use ($order) {
                $variant = $item->productVariant;
                if (! $variant || ! $variant->cj_vid) {
                    throw new FulfillmentException("Missing CJ VID for order item {$item->id}");
                }

                return [
                    'storeLineItemId' => (string) $item->id,
                    'vid' => $variant->cj_vid,
                    'sku' => $variant->sku,
                    'quantity' => $item->quantity,
                    'price' => is_numeric($item->unit_price) ? (float) $item->unit_price : null,
                    'currency' => $order->currency,
                    'title' => $variant->title ?? data_get($item->meta, 'name'),
                ];
            })
            ->values()
            ->all();

        $product = $new_order_items->first()?->productVariant?->product;
        $warehouseId = $product?->cj_warehouse_id ?? $providerSettings['warehouse_id'] ?? null;
        $fromCountry = $product?->cj_warehouse_id
            ? $this->getCountryFromWarehouse($product->cj_warehouse_id)
            : ($providerSettings['from_country'] ?? 'CN');

        $warehouse = $this->ensureDefaultWarehouse();
        $shippingPhone = $warehouse->phone ?? $warehouse->shipping_company_name ?? null;
        $recipientName = $warehouse->shipping_company_name ?? $warehouse->name ?? 'Simbazu Warehouse';

        $shippingRecord = $order->orderShippings
            ->where('fulfillment_provider_id', $data->provider?->id)
            ->first();

        $shippingMethod = $shippingRecord?->name ?? $order->shipping_method ?? 'PostNL';

        $payload = [
            'orderNumber' => $order->number,
            'shippingZip' => $warehouse->postal_code,
            'shippingCountry' => $warehouse->country,
            'shippingCountryCode' => $warehouse->country,
            'shippingProvince' => $warehouse->state,
            'shippingCity' => $warehouse->city,
            'shippingCounty' => null,
            'shippingPhone' => $shippingPhone,
            'shippingCustomerName' => $recipientName,
            'shippingAddress' => $warehouse->line1,
            'shippingAddress2' => $warehouse->line2,
            'taxId' => null,
            'remark' => $order->delivery_notes ?? null,
            'email' => $order->email,
            'consigneeID' => null,
            'payType' => $providerSettings['pay_type'] ?? 3,
            'shopAmount' => is_numeric($order->subtotal) ? (float) $order->subtotal : null,
            'logisticName' => $shippingMethod,
            'fromCountryCode' => $fromCountry,
            'storageId' => $warehouseId ?? $providerSettings['storage_id'] ?? null,
            'products' => $products,
        ];

        $success = false;
        $duplicateHandled = false;
        $body = [];

        try {
            Log::info('Attempting CJ order creation with v2 endpoint', ['order_number' => $data->order_id]);
            $response = $this->client->createOrderV2($payload);
            $body = $this->validatedResponse($response, 'CJ order create v2 failed');
            $success = true;
        } catch (\Throwable $e1) {
            if ($this->isDuplicateOrderException($e1)) {
                $body = $this->resolveExistingOrderSnapshot($payload);
                $duplicateHandled = $body !== null;
                $success = $duplicateHandled;
                if ($duplicateHandled) {
                    Log::info('CJ duplicate order detected; reusing existing order snapshot', [
                        'order_id' => $data->order_id,
                        'order_number' => $payload['orderNumber'] ?? null,
                        'order_data' => $body,
                    ]);
                } else {
                    Log::warning('CJ duplicate order detected but failed to load existing snapshot', [
                        'order_id' => $data->order_id,
                        'order_number' => $payload['orderNumber'] ?? null,
                        'error' => $e1->getMessage(),
                    ]);
                }
            }

            if (! $duplicateHandled) {
                Log::info('V2 endpoint failed, trying V3', ['error' => $e1->getMessage()]);
                try {
                    $response = $this->client->createOrderV3($payload);
                    $body = $this->validatedResponse($response, 'CJ order create v3 failed');
                    $success = true;
                } catch (\Throwable $e2) {
                    if ($this->isDuplicateOrderException($e2)) {
                        $body = $this->resolveExistingOrderSnapshot($payload);
                        $duplicateHandled = $body !== null;
                        $success = $duplicateHandled;
                        if ($duplicateHandled) {
                            Log::info('CJ duplicate order detected via v3 retry; reusing existing snapshot', [
                                'order_id' => $data->order_id,
                                'order_number' => $payload['orderNumber'] ?? null,
                                'order_data' => $body,
                            ]);
                        } else {
                            Log::warning('CJ duplicate order detected during v3 retry but failed to load snapshot', [
                                'order_id' => $data->order_id,
                                'order_number' => $payload['orderNumber'] ?? null,
                                'error' => $e2->getMessage(),
                            ]);
                        }
                    }

                    if (! $duplicateHandled) {
                        Log::warning('CJ fulfillment dispatch failed', [
                            'order_id' => $data->order_id,
                            'provider_id' => $data->provider->id ?? null,
                            'payload' => $payload,
                            'error' => $e2->getMessage(),
                        ]);
                        throw new FulfillmentException('CJ order create failed: ' . $e2->getMessage(), previous: $e2);
                    }
                }
            }
        }

        $externalId = $body['orderId'] ?? $body['orderNumber'] ?? null;
        $shipmentOrderId = $body['shipmentOrderId'] ?? null;
        $trackingNumber = $body['trackingNumber'] ?? null;
        $trackingUrl = $body['trackingUrl'] ?? null;
        $postageAmount = $body['postageAmount'] ?? null;
        $logisticName = $body['logisticName'] ?? $payload['logisticName'] ?? null;

        if ($order && $externalId) {
            $order->update([
                'cj_order_id' => $externalId,
                'cj_shipment_order_id' => $shipmentOrderId,
                'cj_order_status' => 'confirmed',
                'cj_order_created_at' => now(),
                'cj_confirmed_at' => now(),
                'cj_amount_due' => is_numeric($postageAmount) ? (float) $postageAmount : null,
                'cj_payment_status' => 'pending',
            ]);
        }

        return new FulfillmentResult(
            status: $success ? 'succeeded' : 'needs_action',
            externalReference: $externalId,
            cjOrderId: $externalId,
            shipmentOrderId: $shipmentOrderId,
            logisticName: $logisticName,
            currency: $order->currency,
            postageAmount: is_numeric($postageAmount) ? (float) $postageAmount : null,
            trackingNumber: $trackingNumber,
            trackingUrl: $trackingUrl,
            rawResponse: $body ?? []
        );
    }

    private function ensureDefaultWarehouse(): LocalWareHouse
    {
        $warehouse = LocalWareHouse::query()
            ->where('is_default', true)
            ->first();

        if (! $warehouse) {
            throw new FulfillmentException('CJ dispatch requires a default warehouse record.');
        }

        return $warehouse;
    }


//    public function dispatch(FulfillmentRequestData $data): FulfillmentResult
//    {
//        $providerSettings = $data->provider->settings ?? [];
//
//        // Use product's warehouse if available, otherwise fall back to provider settings
//        $product = $data->orderItem->productVariant?->product;
//        $warehouseId = $product?->cj_warehouse_id ?? $providerSettings['warehouse_id'] ?? null;
//        $fromCountry = $product?->cj_warehouse_id ? $this->getCountryFromWarehouse($product->cj_warehouse_id) : ($providerSettings['from_country'] ?? 'CN');
//
//        $payload = [
//            'orderNumber' => $data->orderItem->order?->number,
//            'shippingZip' => $data->shippingAddress?->postal_code,
//            'shippingCountry' => $data->shippingAddress?->country,
//            'shippingCountryCode' => $data->shippingAddress?->country,
//            'shippingProvince' => $data->shippingAddress?->state,
//            'shippingCity' => $data->shippingAddress?->city,
//            'shippingCounty' => null,
//            'shippingPhone' => $data->shippingAddress?->phone,
//            'shippingCustomerName' => $data->shippingAddress?->name,
//            'shippingAddress' => $data->shippingAddress?->line1,
//            'shippingAddress2' => $data->shippingAddress?->line2,
//            'taxId' => null,
//            'remark' => null,
//            'email' => $data->orderItem->order?->email,
//            'consigneeID' => null,
//            'payType' => $providerSettings['pay_type'] ?? 3,
//            'shopAmount' => null,
//            // Use order-selected logisticName when available to keep coherence with freight quote
//            'logisticName' => $data->orderItem->order?->shipping_method ?? ($providerSettings['shipping_method'] ?? 'PostNL'),
//            'fromCountryCode' => $fromCountry,
//            'houseNumber' => null,
//            'iossType' => $providerSettings['ioss_type'] ?? null,
//            'platform' => $providerSettings['platform'] ?? 'Api',
//            'iossNumber' => $providerSettings['ioss_number'] ?? null,
//            'shopLogisticsType' => $providerSettings['shop_logistics_type'] ?? 1,
//            'storageId' => $warehouseId ?? $providerSettings['storage_id'] ?? null,
//            'products' => [
//                [
//                    'vid' => $data->supplierProduct?->external_product_id ?? null,
//                    'sku' => $data->supplierProduct?->external_sku ?? $data->orderItem->productVariant?->sku,
//                    'quantity' => $data->orderItem->quantity ?? 1,
//                    'storeLineItemId' => (string) $data->orderItem->id,
//                ],
//            ],
//        ];
//
//        try {
//            Log::info('Attempting CJ order creation with v2 endpoint', ['order_number' => $data->orderItem->order?->number]);
//            $response = $this->client->createOrderV2($payload);
//            $body = $this->validatedResponse($response, 'CJ order create v2 failed');
//        } catch (\Throwable $e1) {
//            Log::info('V2 endpoint failed, trying V3', ['error' => $e1->getMessage()]);
//            try {
//                $response = $this->client->createOrderV3($payload);
//                $body = $this->validatedResponse($response, 'CJ order create v3 failed');
//            } catch (\Throwable $e2) {
//                Log::warning('CJ fulfillment dispatch failed', [
//                    'order_item_id' => $data->orderItem->id,
//                    'provider_id' => $data->provider->id ?? null,
//                    'payload' => $payload,
//                    'error' => $e2->getMessage(),
//                ]);
//                throw new FulfillmentException('CJ order create failed: ' . $e2->getMessage(), previous: $e2);
//            }
//        }
//
//        $success = Arr::get($body, 'result') === true || Arr::get($body, 'code') === 200;
//        $externalId = Arr::get($body, 'data.orderId') ?? Arr::get($body, 'data.orderNumber');
//        $trackingNumber = Arr::get($body, 'data.trackingNumber');
//        $trackingUrl = Arr::get($body, 'data.trackingUrl');
//        $postageAmount = Arr::get($body, 'data.postageAmount');
//        $currency = Arr::get($body, 'data.currency') ?? Arr::get($body, 'data.currencyCode');
//        $logisticName = Arr::get($body, 'data.logisticName');
//        $shipmentOrderId = Arr::get($body, 'data.shipmentOrderId');
//
//        // PHASE 2: Confirm order to finalize costs and get final payId requirements
//        if ($externalId && $success) {
//            try {
//                Log::info('Confirming CJ order', [
//                    'order_number' => $data->orderItem->order?->number,
//                    'cj_order_id' => $externalId,
//                ]);
//
//                $confirmResponse = $this->client->confirmOrder($externalId);
//                $confirmBody = $this->validatedResponse($confirmResponse, 'CJ order confirm failed');
//
//                // Merge confirmed data with creation data
//                $body['data'] = array_merge($body['data'] ?? [], $confirmBody['data'] ?? []);
//
//                Log::info('CJ order confirmed', [
//                    'order_id' => $externalId,
//                    'confirm_response' => $confirmBody,
//                ]);
//
//            } catch (\Throwable $confirmError) {
//                Log::warning('CJ order confirmation failed, proceeding with creation data', [
//                    'error' => $confirmError->getMessage(),
//                ]);
//                // Don't fail completely if confirmation fails - still have creation data
//            }
//        }
//
//        // Update order with CJ tracking info if we have an order
//        if ($data->orderItem->order && $externalId) {
//            $data->orderItem->order->update([
//                'cj_order_id' => $externalId,
//                'cj_shipment_order_id' => $shipmentOrderId,
//                'cj_order_status' => 'confirmed',
//                'cj_order_created_at' => now(),
//                'cj_confirmed_at' => now(),
//                'cj_amount_due' => is_numeric($postageAmount) ? (float) $postageAmount : null,
//                'cj_payment_status' => 'pending',  // Ready for payment
//            ]);
//        }
//
//        return new FulfillmentResult(
//            status: $success ? 'succeeded' : 'needs_action',
//            externalReference: $externalId,
//            cjOrderId: $externalId,
//            shipmentOrderId: $shipmentOrderId,
//            logisticName: $logisticName ?? $payload['logisticName'] ?? null,
//            currency: $currency,
//            postageAmount: is_numeric($postageAmount) ? (float) $postageAmount : null,
//            trackingNumber: $trackingNumber,
//            trackingUrl: $trackingUrl,
//            rawResponse: $body ?? []
//        );
//    }

    private function isDuplicateOrderException(\Throwable $error): bool
    {
        $message = strtolower((string) $error->getMessage());
        if (str_contains($message, 'order exist') || str_contains($message, 'duplicate')) {
            return true;
        }

        if ($error instanceof ApiException && is_array($error->body)) {
            $body = $error->body;
            $extracted = strtolower((string) ($body['message'] ?? $body['errorMessage'] ?? $body['errorMsg'] ?? ''));
            if (str_contains($extracted, 'order exist') || str_contains($extracted, 'duplicate')) {
                return true;
            }
        }

        return false;
    }

    private function resolveExistingOrderSnapshot(array $payload): ?array
    {
        $orderNumber = $payload['orderNumber'] ?? $payload['orderCode'] ?? null;
        if (!$orderNumber) {
            return null;
        }

        $candidates = [
            fn () => $this->client->getOrderDetail(['orderCode' => $orderNumber]),
            fn () => $this->client->getOrderDetail(['orderNumber' => $orderNumber]),
            fn () => $this->client->getOrderList([
                'pageNum' => 1,
                'pageSize' => 1,
                'orderNumber' => $orderNumber,
            ]),
        ];

        foreach ($candidates as $index => $candidate) {
            try {
                $response = $candidate();
            } catch (\Throwable $lookupError) {
                Log::debug('CJ duplicate order lookup failed', [
                    'order_number' => $orderNumber,
                    'attempt' => $index,
                    'error' => $lookupError->getMessage(),
                ]);
                continue;
            }

            $existing = $this->extractOrderFromResponse($response);
            if (is_array($existing) && !empty($existing)) {
                return $existing;
            }
        }

        return null;
    }

    private function extractOrderFromResponse(ApiResponse $response): ?array
    {
        $data = $response->data;
        if (is_array($data)) {
            if (isset($data['orderId']) || isset($data['orderNumber'])) {
                return $data;
            }

            $list = $data['list'] ?? null;
            if (is_array($list) && count($list)) {
                $first = $list[0] ?? null;
                if (is_array($first)) {
                    return $first;
                }
            }

            if (!empty($data)) {
                return $data;
            }
        }

        return null;
    }

    private function validatedResponse($response, string $context): array
    {
        if (!$response->ok) {
            throw new FulfillmentException("{$context}: " . $response->body());
        }

        return $response->data ?? [];
//        $code = $response?->raw?->code;
 //        $status = Arr::get($body, 'status');
//
//        if ($code && (int)$code !== 200 && strtolower((string)$status) !== 'success') {
//            throw new FulfillmentException("{$context}: " . json_encode($body));
//        }

        return $body;
    }

    private function getCountryFromWarehouse(string $warehouseId): string
    {
        // Map warehouse IDs to country codes
        return match ($warehouseId) {
            'CN' => 'CN',
            'US' => 'US',
            'DE' => 'DE',
            'UK' => 'GB',
            'AU' => 'AU',
            default => 'CN',
        };
    }
}
