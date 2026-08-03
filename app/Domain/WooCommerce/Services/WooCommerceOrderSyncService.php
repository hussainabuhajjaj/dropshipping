<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Shipment;
use App\Domain\WooCommerce\Contracts\WooCommerceClientContract;
use App\Domain\WooCommerce\Contracts\WooCommerceCustomerSyncContract;
use App\Domain\WooCommerce\Contracts\WooCommerceOrderSyncContract;
use App\Domain\WooCommerce\DTOs\WooCommerceSyncResult;
use App\Domain\WooCommerce\Models\WooCommerceOrderMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WooCommerceOrderSyncService implements WooCommerceOrderSyncContract
{
    public function __construct(
        private readonly WooCommerceClientContract $client,
        private readonly WooCommerceCustomerSyncContract $customerSync,
        private readonly WooCommerceLogService $log,
    ) {
    }

    public function syncOrder(Order $order): WooCommerceSyncResult
    {
        $existing = WooCommerceOrderMap::query()
            ->where('order_id', $order->id)
            ->first();

        if ($existing) {
            return $this->updateWooCommerceOrder($order, $existing);
        }

        return $this->createWooCommerceOrder($order);
    }

    public function createWooCommerceOrder(Order $order): WooCommerceSyncResult
    {
        if ($order->payment_status !== 'paid') {
            return WooCommerceSyncResult::skipped('Order payment not confirmed');
        }

        DB::beginTransaction();

        try {
            $existing = WooCommerceOrderMap::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                DB::commit();

                return WooCommerceSyncResult::skipped('Order already synced', $order->id);
            }

            $wooCustomerId = $this->resolveCustomer($order);

            $payload = $this->buildOrderPayload($order, $wooCustomerId);

            $response = $this->client->createOrder($payload);

            $wooOrderId = (int) ($response['id'] ?? 0);
            $wooOrderNumber = (string) ($response['number'] ?? '');

            WooCommerceOrderMap::create([
                'order_id' => $order->id,
                'woocommerce_order_id' => $wooOrderId,
                'woocommerce_order_number' => $wooOrderNumber,
                'status' => 'synced',
                'last_synced_at' => now(),
            ]);

            $this->addOrderNote($order, $wooOrderId);
            $this->logOrderMap($order, $wooOrderId, $wooOrderNumber);

            DB::commit();

            return WooCommerceSyncResult::success($order->id, $wooOrderId, [
                'woocommerce_order_number' => $wooOrderNumber,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('WooCommerce order creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return WooCommerceSyncResult::failed($e->getMessage(), $order->id);
        }
    }

    public function updateOrderStatusFromWebhook(int $woocommerceOrderId, string $status): WooCommerceSyncResult
    {
        $map = WooCommerceOrderMap::query()
            ->where('woocommerce_order_id', $woocommerceOrderId)
            ->first();

        if (! $map || ! $map->order) {
            return WooCommerceSyncResult::skipped('No local order mapping found');
        }

        $order = $map->order;
        $localStatus = $this->mapWebhookStatusToLocal($status);

        if ($localStatus === null) {
            return WooCommerceSyncResult::skipped("No mapping for WooCommerce status: {$status}");
        }

        if ($order->status === $localStatus) {
            return WooCommerceSyncResult::skipped('Order status already matches');
        }

        if ($localStatus === 'delivered') {
            $order->updateCustomerStatus('delivered');
            $order->update(['status' => 'delivered']);
        } elseif ($localStatus === 'cancelled') {
            $order->update(['status' => 'cancelled', 'customer_status' => 'cancelled']);
        } elseif ($localStatus === 'refunded') {
            $order->update(['status' => 'refunded', 'customer_status' => 'refunded']);
        }

        $map->update([
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);

        $this->log->info('order', $order->id, 'status_update', [
            'from' => $order->status,
            'to' => $localStatus,
            'woocommerce_status' => $status,
        ]);

        return WooCommerceSyncResult::success($order->id, $woocommerceOrderId);
    }

    public function updateTrackingFromWebhook(int $woocommerceOrderId, array $trackingData): WooCommerceSyncResult
    {
        $map = WooCommerceOrderMap::query()
            ->where('woocommerce_order_id', $woocommerceOrderId)
            ->first();

        if (! $map || ! $map->order) {
            return WooCommerceSyncResult::skipped('No local order mapping found');
        }

        $order = $map->order;
        $trackingNumber = $trackingData['tracking_number'] ?? $trackingData['trackingNumber'] ?? null;
        $carrier = $trackingData['carrier'] ?? $trackingData['provider'] ?? null;
        $trackingUrl = $trackingData['tracking_url'] ?? $trackingData['trackingUrl'] ?? null;

        if (! $trackingNumber) {
            return WooCommerceSyncResult::skipped('No tracking number provided');
        }

        foreach ($order->orderItems as $orderItem) {
            Shipment::query()->updateOrCreate(
                [
                    'order_item_id' => $orderItem->id,
                    'tracking_number' => $trackingNumber,
                ],
                [
                    'order_id' => $order->id,
                    'carrier' => $carrier,
                    'tracking_url' => $trackingUrl,
                    'shipped_at' => now(),
                ],
            );
        }

        $order->updateCustomerStatus('shipped');
        $order->update(['status' => 'shipped']);

        $this->log->info('order', $order->id, 'tracking_update', [
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
        ]);

        return WooCommerceSyncResult::success($order->id, $woocommerceOrderId);
    }

    private function resolveCustomer(Order $order): ?int
    {
        $customer = $order->customer;

        if (! $customer) {
            return null;
        }

        try {
            return $this->customerSync->findOrCreateWooCommerceCustomer($customer);
        } catch (\Throwable $e) {
            Log::warning('Could not sync customer to WooCommerce, proceeding without', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildOrderPayload(Order $order, ?int $wooCustomerId): array
    {
        $order->loadMissing([
            'orderItems.productVariant.product',
            'shippingAddress',
            'billingAddress',
        ]);

        $items = [];
        foreach ($order->orderItems as $item) {
            $productMap = $this->resolveProductMapping($item);

            $lineItem = [
                'product_id' => $productMap['product_id'],
                'variation_id' => $productMap['variation_id'],
                'quantity' => $item->quantity,
                'price' => (string) $item->unit_price,
                'total' => (string) $item->total,
                'name' => $item->productVariant?->title ?? $item->meta['name'] ?? 'Product',
                'sku' => $item->productVariant?->sku ?? '',
                'meta_data' => [
                    ['key' => '_laravel_order_item_id', 'value' => (string) $item->id],
                ],
            ];

            $items[] = $lineItem;
        }

        $billing = $this->buildAddressPayload($order->billingAddress, $order);
        $shipping = $this->buildAddressPayload($order->shippingAddress, $order);

        $payload = [
            'customer_id' => $wooCustomerId ?? 0,
            'customer_note' => $order->delivery_notes ?? '',
            'payment_method' => $order->payments->first()?->provider ?? 'paystack',
            'payment_method_title' => ucfirst($order->payments->first()?->provider ?? 'Online Payment'),
            'transaction_id' => $order->payments->first()?->provider_reference ?? '',
            'status' => $this->mapOrderStatus((string) $order->status),
            'currency' => $order->currency ?? 'USD',
            'prices_include_tax' => false,
            'line_items' => $items,
            'shipping_lines' => [
                [
                    'method_title' => $order->shipping_method ?? 'Standard Shipping',
                    'method_id' => strtolower(str_replace(' ', '-', $order->shipping_method ?? 'standard')),
                    'total' => (string) ($order->shipping_total ?? '0.00'),
                ],
            ],
            'fee_lines' => $this->buildFeeLines($order),
            'coupon_lines' => $this->buildCouponLines($order),
            'billing' => $billing,
            'shipping' => $shipping,
            'meta_data' => [
                ['key' => '_laravel_order_id', 'value' => (string) $order->id],
                ['key' => '_laravel_order_number', 'value' => $order->number],
                ['key' => '_laravel_sync_source', 'value' => 'laravel'],
            ],
        ];

        return $payload;
    }

    private function buildAddressPayload(?\App\Domain\Common\Models\Address $address, Order $order): array
    {
        return [
            'first_name' => $address?->name ?? $order->customer?->first_name ?? '',
            'last_name' => '',
            'address_1' => $address?->line1 ?? '',
            'address_2' => $address?->line2 ?? '',
            'city' => $address?->city ?? '',
            'state' => $address?->state ?? '',
            'postcode' => $address?->postal_code ?? '',
            'country' => $address?->country ?? '',
            'email' => $order->email ?? $address?->email ?? '',
            'phone' => $address?->phone ?? $order->customer?->phone ?? '',
        ];
    }

    private function resolveProductMapping(OrderItem $item): array
    {
        $productId = null;
        $variationId = null;

        if ($item->product_variant_id) {
            $map = \App\Domain\WooCommerce\Models\WooCommerceProductMap::query()
                ->where('product_variant_id', $item->product_variant_id)
                ->first();

            if ($map) {
                return [
                    'product_id' => $map->woocommerce_product_id,
                    'variation_id' => $map->woocommerce_variation_id ?? 0,
                ];
            }
        }

        if ($item->productVariant?->product_id) {
            $map = \App\Domain\WooCommerce\Models\WooCommerceProductMap::query()
                ->where('product_id', $item->productVariant->product_id)
                ->first();

            if ($map) {
                return [
                    'product_id' => $map->woocommerce_product_id,
                    'variation_id' => 0,
                ];
            }
        }

        return [
            'product_id' => 0,
            'variation_id' => 0,
        ];
    }

    private function buildFeeLines(Order $order): array
    {
        $fees = [];

        if ($order->tax_total > 0) {
            $fees[] = [
                'name' => 'Tax',
                'total' => (string) $order->tax_total,
                'taxable' => false,
            ];
        }

        return $fees;
    }

    private function buildCouponLines(Order $order): array
    {
        $coupons = [];

        if ($order->coupon_code) {
            $coupons[] = [
                'code' => $order->coupon_code,
                'discount' => (string) ($order->discount_total ?? 0),
            ];
        }

        return $coupons;
    }

    private function updateWooCommerceOrder(Order $order, WooCommerceOrderMap $map): WooCommerceSyncResult
    {
        try {
            $payload = [
                'status' => $this->mapOrderStatus((string) $order->status),
            ];

            if ($order->tracking_number) {
                $payload['meta_data'] = [
                    ['key' => '_tracking_number', 'value' => $order->tracking_number],
                    ['key' => '_carrier', 'value' => $order->carrier ?? ''],
                    ['key' => '_tracking_url', 'value' => $order->tracking_url ?? ''],
                ];
            }

            $this->client->updateOrder($map->woocommerce_order_id, $payload);

            $map->update(['last_synced_at' => now()]);

            return WooCommerceSyncResult::success($order->id, $map->woocommerce_order_id);
        } catch (\Throwable $e) {
            Log::error('WooCommerce order update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return WooCommerceSyncResult::failed($e->getMessage(), $order->id);
        }
    }

    private function addOrderNote(Order $order, int $wooOrderId): void
    {
        try {
            $this->client->addOrderNote(
                $wooOrderId,
                sprintf(
                    'Order synced from Laravel (#%s). Payment: %s | Total: %s %s',
                    $order->number,
                    $order->payment_status,
                    $order->grand_total ?? $order->total,
                    $order->currency,
                ),
                false,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to add WooCommerce order note', [
                'order_id' => $order->id,
                'woo_order_id' => $wooOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logOrderMap(Order $order, int $wooOrderId, string $wooOrderNumber): void
    {
        $this->log->info('order', $order->id, 'create', [
            'woocommerce_order_id' => $wooOrderId,
            'woocommerce_order_number' => $wooOrderNumber,
        ]);
    }

    private function mapOrderStatus(string $status): string
    {
        $map = config('woocommerce.order_status_map', []);

        return (string) ($map[$status] ?? $status);
    }

    private function mapWebhookStatusToLocal(string $status): ?string
    {
        $map = config('woocommerce.webhook_status_map', []);

        return $map[$status] ?? null;
    }
}
