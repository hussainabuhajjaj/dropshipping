<?php

declare(strict_types=1);

namespace App\Listeners\Orders;

use App\Events\Orders\OrderPaid;
use App\Services\Meta\MetaConversionsApiService;

class SendMetaPurchaseEvent
{
    public function __construct(
        private readonly MetaConversionsApiService $metaConversionsApi,
    ) {
    }

    public function handle(OrderPaid $event): void
    {
        if (! $this->metaConversionsApi->enabled()) {
            return;
        }

        $order = $event->order->loadMissing([
            'customer',
            'shippingAddress',
            'orderItems',
            'payments' => fn ($query) => $query->latest('id'),
        ]);

        $payment = $order->payments->first();
        if (! $payment) {
            return;
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $metaAds = is_array($meta['meta_ads'] ?? null) ? $meta['meta_ads'] : null;
        if (! $metaAds) {
            return;
        }

        $platform = strtolower((string) ($metaAds['platform'] ?? ''));
        $advertiserTrackingEnabled = (int) ($metaAds['advertiser_tracking_enabled'] ?? 0);
        $applicationTrackingEnabled = (int) ($metaAds['application_tracking_enabled'] ?? 0);
        $alreadySentAt = $meta['meta_ads_purchase_sent_at'] ?? null;

        if ($alreadySentAt) {
            return;
        }

        if ($platform === 'ios' && $advertiserTrackingEnabled !== 1) {
            return;
        }

        $emailHash = $this->metaConversionsApi->normalizeAndHash($order->email);
        $phoneHash = $this->metaConversionsApi->normalizeAndHashPhone(
            $order->shippingAddress?->phone
                ?? $order->customer?->phone
                ?? null,
        );

        $externalId = $order->customer_id
            ? $this->metaConversionsApi->normalizeAndHash((string) $order->customer_id)
            : ($emailHash ?: null);

        $userData = array_filter([
            'em' => $emailHash ? [$emailHash] : null,
            'ph' => $phoneHash ? [$phoneHash] : null,
            'external_id' => $externalId ? [$externalId] : null,
            'anon_id' => is_string($metaAds['anon_id'] ?? null) ? $metaAds['anon_id'] : null,
            'madid' => is_string($metaAds['madid'] ?? null) ? $metaAds['madid'] : null,
        ], fn ($value) => $value !== null && $value !== []);

        if ($userData === []) {
            return;
        }

        $contentIds = $order->orderItems
            ->pluck('product_variant_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $appData = [
            'advertiser_tracking_enabled' => $advertiserTrackingEnabled === 1,
            'application_tracking_enabled' => $applicationTrackingEnabled === 1,
            'extinfo' => array_values(array_map(
                static fn ($value) => is_scalar($value) ? (string) $value : '',
                is_array($metaAds['extinfo'] ?? null) ? $metaAds['extinfo'] : [],
            )),
        ];

        $eventId = sprintf('mobile-purchase-%s', (string) $order->number);
        $sent = $this->metaConversionsApi->sendAppEvent([
            'event_name' => 'Purchase',
            'event_time' => $payment->paid_at?->timestamp ?? now()->timestamp,
            'event_id' => $eventId,
            'action_source' => 'app',
            'user_data' => $userData,
            'custom_data' => array_filter([
                'currency' => (string) $order->currency,
                'value' => number_format((float) $order->grand_total, 2, '.', ''),
                'order_id' => (string) $order->number,
                'num_items' => (int) $order->orderItems->sum('quantity'),
                'content_type' => 'product',
                'content_ids' => $contentIds,
            ], static fn ($value) => $value !== null && $value !== []),
            'app_data' => $appData,
        ]);

        if (! $sent) {
            return;
        }

        $meta['meta_ads_purchase_sent_at'] = now()->toISOString();
        $meta['meta_ads_purchase_event_id'] = $eventId;
        $payment->update(['meta' => $meta]);
    }
}
