<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Fulfillment\Models\FulfillmentJob;
use App\Domain\Fulfillment\Strategies\CJDropshippingFulfillmentStrategy;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Products\Services\CjProductImportService;
use App\Enums\RefundReasonEnum;
use App\Http\Controllers\Controller;
use App\Models\CJWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CJWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->verifySignature($request);

        // Read JSON body (or fallback to form data)
        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }

        $messageId = $payload['messageId'] ?? null;
        $requestId = $payload['requestId'] ?? null;

        // Idempotency: if we already saw this messageId, skip processing to avoid double-work.
        if ($messageId !== null) {
            $existing = CJWebhookLog::query()->where('message_id', $messageId)->first();
            if ($existing) {
                // Update attempts and keep a short trace but don't re-process
                $existing->increment('attempts');
                Log::info('Duplicate CJ webhook received; skipping processing', [
                    'message_id' => $messageId,
                    'existing_id' => $existing->id,
                ]);

                return response()->json(['ok' => true]);
            }
        }

        $log = CJWebhookLog::create([
            'message_id' => $messageId,
            'request_id' => $requestId,
            'type' => $payload['type'] ?? null,
            'message_type' => $payload['messageType'] ?? null,
            'payload' => $payload,
            'attempts' => 0,
            'processed' => false,
        ]);

        Log::info('CJ webhook received', [
            'id' => $log->id,
            'message_id' => $log->message_id,
            'request_id' => $log->request_id,
            'type' => $log->type,
            'message_type' => $log->message_type,
        ]);

        // Process, and record processing result for observability and retries
        try {
            $this->handleOrderStatus($payload);
            $this->handleProductSync($payload);

            $log->update([
                'processed' => true,
                'processed_at' => now(),
                'attempts' => $log->attempts + 1,
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'attempts' => $log->attempts + 1,
                'last_error' => substr($e->getMessage(), 0, 2000),
            ]);

            Log::warning('CJ webhook processing failed', [
                'id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Keep response under 3 seconds — defer heavy work to queues if needed.
        return response()->json(['ok' => true]);
    }

    private function handleOrderStatus(array $payload): void
    {
        $externalId = Arr::get($payload, 'orderId') ?? Arr::get($payload, 'data.orderId');

        if (! $externalId) {
            return;
        }

        $job = FulfillmentJob::with('provider')->where('external_reference', $externalId)->first();
        if (! $job || $job->provider?->driver_class !== CJDropshippingFulfillmentStrategy::class) {
            return;
        }

        $status = strtolower((string) (Arr::get($payload, 'status') ?? ''));
        $trackingNumber = Arr::get($payload, 'trackingNumber');
        $trackingUrl = Arr::get($payload, 'trackingUrl');

        $job->status = match ($status) {
            'completed', 'success', 'fulfilled' => 'succeeded',
            'failed', 'cancelled' => 'failed',
            default => $job->status,
        };
        $job->fulfilled_at = $job->status === 'succeeded' ? now() : $job->fulfilled_at;
        $job->last_error = Arr::get($payload, 'errorMsg', $job->last_error);
        $job->save();

        // Update Order customer_status based on fulfillment job status
        $order = Order::find($job->order_id);
        if ($order) {
            // If fulfillment failed, auto-refund if enabled
            if ($job->status === 'failed' && config('app.orders.auto_approve_refunds', true)) {
                // Auto-refund disabled for now - requires proper enum definition
                /** @noinspection PhpUndefinedClassInspection */
                \Log::warning('CJ fulfillment failed for order ' . $order->id . ': ' . $job->last_error);
            } elseif ($job->status === 'succeeded') {
                // CJ confirmed the order
                $order->updateCustomerStatus('dispatched');
            }
        }

        if ($trackingNumber) {
            Shipment::updateOrCreate(
                ['order_item_id' => $job->order_item_id, 'tracking_number' => $trackingNumber],
                [
                    'carrier' => Arr::get($payload, 'carrier'),
                    'tracking_url' => $trackingUrl,
                    'logistic_name' => Arr::get($payload, 'logisticName'),
                    'cj_order_id' => $externalId,
                    'shipment_order_id' => Arr::get($payload, 'shipmentOrderId'),
                    'postage_amount' => Arr::get($payload, 'postageAmount'),
                    'currency' => Arr::get($payload, 'currency') ?? Arr::get($payload, 'currencyCode'),
                    'shipped_at' => Arr::get($payload, 'shippedAt') ?? now(),
                    'raw_events' => Arr::get($payload, 'events'),
                ]
            );

            // Reconcile order-level shipping totals based on shipment postage amounts
            if ($order) {
                $actual = (float) ($order->shipments()->sum('postage_amount') ?? 0);
                $estimated = (float) ($order->shipping_total_estimated ?? $order->shipping_total ?? 0);
                $order->update([
                    'shipping_total_actual' => $actual,
                    'shipping_variance' => round($actual - $estimated, 2),
                    'shipping_reconciled_at' => now(),
                ]);

                // If we have tracking, mark as in_transit
                $order->updateCustomerStatus('in_transit');
            }
        }
    }

    private function handleProductSync(array $payload): void
    {
        $orderId = $this->extractValue($payload, ['orderId', 'data.orderId']);
        if ($orderId) {
            return;
        }

        $pid = $this->extractValue($payload, ['pid', 'productId', 'product_id', 'data.pid', 'data.productId']);
        $productSku = $this->extractValue($payload, ['productSku', 'productSKU', 'data.productSku', 'data.productSKU']);
        $variantSku = $this->extractValue($payload, ['variantSku', 'variantSKU', 'data.variantSku', 'data.variantSKU', 'sku', 'data.sku']);

        if (! $pid && ! $productSku && ! $variantSku) {
            return;
        }

        $importer = app(CjProductImportService::class);

        $lookupType = $pid ? 'pid' : ($productSku ? 'productSku' : 'variantSku');
        $lookupValue = $pid ?: ($productSku ?: $variantSku);

        if (! $lookupValue) {
            return;
        }

        try {
            $importer->importByLookup($lookupType, $lookupValue, [
                'respectSyncFlag' => true,
                'respectLocks' => true,
                'syncImages' => true,
                'syncVariants' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CJ webhook product sync failed', [
                'lookup_type' => $lookupType,
                'lookup_value' => $lookupValue,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function verifySignature(Request $request): void
    {
        $secret = config('services.cj.webhook_secret');
        if (! $secret) {
            return;
        }

        $provided = $request->header('CJ-SIGN') ?? $request->header('cj-sign');
        if (! $provided) {
            abort(401, 'Missing CJ signature');
        }

        // Prevent replay attacks if CJ includes a timestamp header
        $timestampHeader = $request->header('CJ-TIMESTAMP') ?? $request->header('cj-timestamp');
        if ($timestampHeader) {
            $age = abs((int) $timestampHeader - (int) (microtime(true) * 1000));
            $maxAgeMs = config('services.cj.webhook_max_age_ms', 5 * 60 * 1000); // 5 minutes default
            if ($age > $maxAgeMs) {
                abort(401, 'CJ webhook timestamp outside acceptable window');
            }
        }

        $computed = Str::lower(hash_hmac('sha256', $request->getContent(), $secret));
        if (! hash_equals($computed, Str::lower($provided))) {
            abort(401, 'Invalid CJ signature');
        }
    }
}
