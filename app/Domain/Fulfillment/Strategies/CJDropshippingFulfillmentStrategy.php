<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Strategies;

use App\Domain\Fulfillment\Contracts\FulfillmentStrategy;
use App\Domain\Fulfillment\DTOs\FulfillmentRequestData;
use App\Domain\Fulfillment\DTOs\FulfillmentResult;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class CJDropshippingFulfillmentStrategy implements FulfillmentStrategy
{
    public function __construct(private readonly CJDropshippingClient $client)
    {
    }

    public function dispatch(FulfillmentRequestData $data): FulfillmentResult
    {
        $providerSettings = $data->provider->settings ?? [];
        
        // Use product's warehouse if available, otherwise fall back to provider settings
        $product = $data->orderItem->productVariant?->product;
        $warehouseId = $product?->cj_warehouse_id ?? $providerSettings['warehouse_id'] ?? null;
        $fromCountry = $product?->cj_warehouse_id ? $this->getCountryFromWarehouse($product->cj_warehouse_id) : ($providerSettings['from_country'] ?? 'CN');
        
        $payload = [
            'orderNumber' => $data->orderItem->order?->number,
            'shippingZip' => $data->shippingAddress?->postal_code,
            'shippingCountry' => $data->shippingAddress?->country,
            'shippingCountryCode' => $data->shippingAddress?->country,
            'shippingProvince' => $data->shippingAddress?->state,
            'shippingCity' => $data->shippingAddress?->city,
            'shippingCounty' => null,
            'shippingPhone' => $data->shippingAddress?->phone,
            'shippingCustomerName' => $data->shippingAddress?->name,
            'shippingAddress' => $data->shippingAddress?->line1,
            'shippingAddress2' => $data->shippingAddress?->line2,
            'taxId' => null,
            'remark' => null,
            'email' => $data->orderItem->order?->email,
            'consigneeID' => null,
            'payType' => $providerSettings['pay_type'] ?? 3,
            'shopAmount' => null,
            // Use order-selected logisticName when available to keep coherence with freight quote
            'logisticName' => $data->orderItem->order?->shipping_method ?? ($providerSettings['shipping_method'] ?? 'PostNL'),
            'fromCountryCode' => $fromCountry,
            'houseNumber' => null,
            'iossType' => $providerSettings['ioss_type'] ?? null,
            'platform' => $providerSettings['platform'] ?? 'Api',
            'iossNumber' => $providerSettings['ioss_number'] ?? null,
            'shopLogisticsType' => $providerSettings['shop_logistics_type'] ?? 1,
            'storageId' => $warehouseId ?? $providerSettings['storage_id'] ?? null,
            'products' => [
                [
                    'vid' => $data->supplierProduct?->external_product_id ?? null,
                    'sku' => $data->supplierProduct?->external_sku ?? $data->orderItem->productVariant?->sku,
                    'quantity' => $data->orderItem->quantity ?? 1,
                    'storeLineItemId' => (string) $data->orderItem->id,
                ],
            ],
        ];

        try {
            // Try endpoints in order: v2, v3, then legacy /order/create
            try {
                Log::info('Attempting CJ order creation with v2 endpoint', ['order_number' => $data->orderItem->order?->number]);
                $response = $this->client->createOrderV2($payload);
                $body = $this->validatedResponse($response, 'CJ order create v2 failed');
            } catch (\Throwable $e1) {
                try {
                    Log::info('V2 endpoint failed, trying V3', ['error' => $e1->getMessage()]);
                    $response = $this->client->createOrderV3($payload);
                    $body = $this->validatedResponse($response, 'CJ order create v3 failed');
                } catch (\Throwable $e2) {
                    Log::info('V3 endpoint failed, trying legacy /order/create', ['error' => $e2->getMessage()]);
                    $response = $this->client->createOrder($payload);
                    $body = $this->validatedResponse($response, 'CJ order create failed');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('CJ fulfillment dispatch failed', [
                'order_item_id' => $data->orderItem->id,
                'provider_id' => $data->provider->id ?? null,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            throw new FulfillmentException('CJ order create failed: ' . $e->getMessage(), previous: $e);
        }

        $success = Arr::get($body, 'result') === true || Arr::get($body, 'code') === 200;
        $externalId = Arr::get($body, 'data.orderId') ?? Arr::get($body, 'data.orderNumber');
        $trackingNumber = Arr::get($body, 'data.trackingNumber');
        $trackingUrl = Arr::get($body, 'data.trackingUrl');
        $postageAmount = Arr::get($body, 'data.postageAmount');
        $currency = Arr::get($body, 'data.currency') ?? Arr::get($body, 'data.currencyCode');
        $logisticName = Arr::get($body, 'data.logisticName');
        $shipmentOrderId = Arr::get($body, 'data.shipmentOrderId');

        // PHASE 2: Confirm order to finalize costs and get final payId requirements
        if ($externalId && $success) {
            try {
                Log::info('Confirming CJ order', [
                    'order_number' => $data->orderItem->order?->number,
                    'cj_order_id' => $externalId,
                ]);

                $confirmResponse = $this->client->confirmOrder($externalId);
                $confirmBody = $this->validatedResponse($confirmResponse, 'CJ order confirm failed');

                // Merge confirmed data with creation data
                $body['data'] = array_merge($body['data'] ?? [], $confirmBody['data'] ?? []);

                Log::info('CJ order confirmed', [
                    'order_id' => $externalId,
                    'confirm_response' => $confirmBody,
                ]);

            } catch (\Throwable $confirmError) {
                Log::warning('CJ order confirmation failed, proceeding with creation data', [
                    'error' => $confirmError->getMessage(),
                ]);
                // Don't fail completely if confirmation fails - still have creation data
            }
        }

        // Update order with CJ tracking info if we have an order
        if ($data->orderItem->order && $externalId) {
            $data->orderItem->order->update([
                'cj_order_id' => $externalId,
                'cj_shipment_order_id' => $shipmentOrderId,
                'cj_order_status' => 'confirmed',
                'cj_order_created_at' => now(),
                'cj_confirmed_at' => now(),
                'cj_amount_due' => is_numeric($postageAmount) ? (float) $postageAmount : null,
                'cj_payment_status' => 'pending',  // Ready for payment
            ]);
        }

        return new FulfillmentResult(
            status: $success ? 'succeeded' : 'needs_action',
            externalReference: $externalId,
            cjOrderId: $externalId,
            shipmentOrderId: $shipmentOrderId,
            logisticName: $logisticName ?? $payload['logisticName'] ?? null,
            currency: $currency,
            postageAmount: is_numeric($postageAmount) ? (float) $postageAmount : null,
            trackingNumber: $trackingNumber,
            trackingUrl: $trackingUrl,
            rawResponse: $body ?? []
        );
    }

    private function formatAddress(FulfillmentRequestData $data): array
    {
        $addr = $data->shippingAddress;
        if (! $addr) {
            throw new FulfillmentException('Missing shipping address for CJ dispatch.');
        }

        return [
            'name' => $addr->name,
            'phone' => $addr->phone,
            'countryCode' => $addr->country,
            'state' => $addr->state,
            'city' => $addr->city,
            'address1' => $addr->line1,
            'address2' => $addr->line2,
            'zip' => $addr->postal_code,
        ];
    }

    private function validatedResponse($response, string $context): array
    {
        if ($response->failed()) {
            throw new FulfillmentException("{$context}: " . $response->body());
        }

        $body = $response->json() ?? [];
        $code = Arr::get($body, 'code');
        $status = Arr::get($body, 'status');

        if ($code && (int) $code !== 200 && strtolower((string) $status) !== 'success') {
            throw new FulfillmentException("{$context}: " . json_encode($body));
        }

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
