<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Services;

use App\Domain\WooCommerce\Models\WooCommerceWebhookLog;
use App\Domain\WooCommerce\Webhooks\WooCommerceWebhookVerifier;
use Illuminate\Support\Facades\Log;

class WooCommerceWebhookHandlerService
{
    public function __construct(
        private readonly WooCommerceWebhookVerifier $verifier,
        private readonly WooCommerceOrderSyncService $orderSync,
        private readonly WooCommerceCustomerSyncService $customerSync,
        private readonly WooCommerceProductSyncService $productSync,
        private readonly WooCommerceLogService $log,
    ) {
    }

    public function handle(
        string $eventType,
        array $payload,
        string $rawPayload,
        string $signature,
        string $deliveryId = '',
        string $resource = '',
        string $event = '',
    ): array {
        $webhookId = $payload['id'] ?? $payload['webhook_id'] ?? null;

        if (! $this->verifier->verify($rawPayload, $signature, $webhookId)) {
            Log::warning('WooCommerce webhook rejected: invalid signature', [
                'event_type' => $eventType,
                'webhook_id' => $webhookId,
            ]);

            return ['status' => 'rejected', 'reason' => 'Invalid signature'];
        }

        $log = WooCommerceWebhookLog::create([
            'webhook_id' => (string) ($webhookId ?? ''),
            'delivery_id' => $deliveryId !== '' ? $deliveryId : null,
            'event_type' => $eventType,
            'resource' => $resource !== '' ? $resource : null,
            'event' => $event !== '' ? $event : null,
            'payload' => $payload,
            'status' => 'received',
        ]);

        try {
            $result = match (true) {
                str_starts_with($eventType, 'order.') => $this->handleOrderEvent($eventType, $payload),
                str_starts_with($eventType, 'customer.') => $this->handleCustomerEvent($eventType, $payload),
                str_starts_with($eventType, 'product.') => $this->handleProductEvent($eventType, $payload),
                default => ['handled' => false, 'reason' => "Unknown event type: {$eventType}"],
            };

            $log->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            $this->log->info('webhook', null, 'processed', [
                'event_type' => $eventType,
                'webhook_id' => $webhookId,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('WooCommerce webhook processing failed', [
                'event_type' => $eventType,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function handleOrderEvent(string $eventType, array $payload): array
    {
        $wooOrderId = (int) ($payload['id'] ?? 0);

        if ($wooOrderId === 0) {
            return ['handled' => false, 'reason' => 'No order ID in payload'];
        }

        return match ($eventType) {
            'order.updated', 'order.created' => $this->processOrderUpdate($wooOrderId, $payload),
            'order.deleted' => ['handled' => true, 'action' => 'order_deleted_ignored'],
            default => ['handled' => false, 'reason' => "Unhandled order event: {$eventType}"],
        };
    }

    private function processOrderUpdate(int $wooOrderId, array $payload): array
    {
        $status = $payload['status'] ?? '';

        $result = $this->orderSync->updateOrderStatusFromWebhook($wooOrderId, (string) $status);

        $trackingResult = null;
        if (! empty($payload['shipment_trackings']) || ! empty($payload['meta_data'])) {
            $tracking = $this->extractTrackingData($payload);
            if ($tracking !== null) {
                $trackingResult = $this->orderSync->updateTrackingFromWebhook($wooOrderId, $tracking);
            }
        }

        return [
            'handled' => true,
            'action' => 'order_updated',
            'status' => $status,
            'status_sync' => $result->success ? 'synced' : 'failed',
            'tracking_sync' => $trackingResult?->success ?? 'none',
        ];
    }

    private function handleCustomerEvent(string $eventType, array $payload): array
    {
        $wooCustomerId = (int) ($payload['id'] ?? 0);

        if ($wooCustomerId === 0) {
            return ['handled' => false, 'reason' => 'No customer ID in payload'];
        }

        return match ($eventType) {
            'customer.updated' => [
                'handled' => true,
                'action' => 'customer_updated',
                'result' => $this->customerSync->updateCustomerFromWebhook($wooCustomerId, $payload)->status,
            ],
            default => ['handled' => false, 'reason' => "Unhandled customer event: {$eventType}"],
        };
    }

    private function handleProductEvent(string $eventType, array $payload): array
    {
        $wooProductId = (int) ($payload['id'] ?? 0);

        if ($wooProductId === 0) {
            return ['handled' => false, 'reason' => 'No product ID in payload'];
        }

        return match ($eventType) {
            'product.updated' => [
                'handled' => true,
                'action' => 'product_updated',
                'result' => $this->productSync->importProductFromWooCommerce($wooProductId)->status,
            ],
            'product.deleted' => ['handled' => true, 'action' => 'product_deleted_ignored'],
            default => ['handled' => false, 'reason' => "Unhandled product event: {$eventType}"],
        };
    }

    private function extractTrackingData(array $payload): ?array
    {
        if (! empty($payload['shipment_trackings'])) {
            $tracking = $payload['shipment_trackings'][0];

            return [
                'tracking_number' => $tracking['tracking_number'] ?? null,
                'carrier' => $tracking['carrier'] ?? null,
                'tracking_url' => $tracking['tracking_url'] ?? null,
            ];
        }

        $meta = collect($payload['meta_data'] ?? []);
        $trackingNumber = $meta->firstWhere('key', '_tracking_number')['value'] ?? null;

        if ($trackingNumber) {
            return [
                'tracking_number' => $trackingNumber,
                'carrier' => $meta->firstWhere('key', '_carrier')['value'] ?? null,
                'tracking_url' => $meta->firstWhere('key', '_tracking_url')['value'] ?? null,
            ];
        }

        return null;
    }
}
