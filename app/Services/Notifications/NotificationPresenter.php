<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    public function format(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data ?? null) ? $notification->data : [];
        $type = $notification->type;

        return [
            'id' => $notification->id,
            'type' => $type,
            'title' => $data['title'] ?? $this->notificationTitle($type, $data),
            'body' => $data['body'] ?? $this->notificationBody($type, $data),
            'action_url' => $data['action_url'] ?? $data['tracking_url'] ?? $data['order_url'] ?? $data['admin_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }

    private function notificationTitle(string $type, array $data): string
    {
        if (str_contains($type, 'OrderConfirmedNotification')) {
            return "Order #{$data['order_number']} confirmed";
        }
        if (str_contains($type, 'DeliveryConfirmedNotification')) {
            return "Order #{$data['order_number']} delivered";
        }
        if (str_contains($type, 'ShippingDelayNotification')) {
            return "Delay on order #{$data['order_number']}";
        }
        if (str_contains($type, 'CustomsInfoNotification')) {
            return "Customs update for #{$data['order_number']}";
        }
        if (str_contains($type, 'RefundProcessedNotification')) {
            return "Refund processed for #{$data['order_number']}";
        }
        if (str_contains($type, 'CustomerShipmentNotification')) {
            return "Shipment update for #{$data['order_number']}";
        }
        if (str_contains($type, 'OrderCancellationConfirmedNotification')) {
            return "Order #{$data['order_number']} cancelled";
        }
        if (str_contains($type, 'PaymentReceiptNotification')) {
            return "Payment received for #{$data['order_number']}";
        }
        if (str_contains($type, 'ReturnApprovedNotification')) {
            return "Return approved for #{$data['order_number']}";
        }
        if (str_contains($type, 'ReturnRejectedNotification')) {
            return "Return update for #{$data['order_number']}";
        }
        if (str_contains($type, 'InTransitNotification')) {
            return "Order #{$data['order_number']} is on the way";
        }
        if (str_contains($type, 'OrderStatusChanged')) {
            return "Order #{$data['order_number']} updated";
        }
        if (str_contains($type, 'WelcomeNotification')) {
            return 'Welcome to our store';
        }
        if (str_contains($type, 'AbandonedCartNotification')) {
            return 'You left items in your cart';
        }
        if (str_contains($type, 'ReviewRequestNotification')) {
            return 'Review your purchase';
        }

        return $data['title'] ?? 'Notification';
    }

    private function notificationBody(string $type, array $data): string
    {
        if (str_contains($type, 'OrderConfirmedNotification')) {
            return "Total: {$data['currency']} {$data['total']}";
        }
        if (str_contains($type, 'DeliveryConfirmedNotification')) {
            return $data['delivered_at'] ? "Delivered at {$data['delivered_at']}." : 'Delivery confirmed.';
        }
        if (str_contains($type, 'ShippingDelayNotification')) {
            return trim(implode(' ', array_filter([
                $data['reason'] ?? null,
                $data['eta'] ? "ETA: {$data['eta']}" : null,
            ]))) ?: 'Shipping delay reported.';
        }
        if (str_contains($type, 'CustomsInfoNotification')) {
            return $data['note'] ?? 'Your shipment is being processed by customs.';
        }
        if (str_contains($type, 'RefundProcessedNotification')) {
            return "Refund {$data['currency']} {$data['amount']}" . (! empty($data['reason']) ? " · {$data['reason']}" : '');
        }
        if (str_contains($type, 'CustomerShipmentNotification')) {
            $tracking = $data['tracking_number'] ?? null;
            return $tracking ? "Tracking: {$tracking}" : 'Shipment update received.';
        }
        if (str_contains($type, 'OrderCancellationConfirmedNotification')) {
            $refund = $data['refund_amount'] ?? null;
            return $refund ? "Refund: {$refund}" : 'Your order was cancelled.';
        }
        if (str_contains($type, 'PaymentReceiptNotification')) {
            $method = $data['payment_method'] ?? null;
            $amount = isset($data['amount']) ? "{$data['currency']} {$data['amount']}" : null;
            return trim(implode(' · ', array_filter([
                $amount ? "Paid {$amount}" : null,
                $method ? ucfirst(str_replace('_', ' ', (string) $method)) : null,
            ]))) ?: 'Payment received.';
        }
        if (str_contains($type, 'ReturnApprovedNotification')) {
            return $data['return_label_url'] ? 'Return approved · label ready' : 'Return approved · ship item back';
        }
        if (str_contains($type, 'ReturnRejectedNotification')) {
            return $data['rejection_reason'] ?? 'Return request rejected.';
        }
        if (str_contains($type, 'InTransitNotification')) {
            $tracking = $data['tracking_number'] ?? null;
            return $tracking ? "Tracking: {$tracking}" : 'Your order is in transit.';
        }
        if (str_contains($type, 'OrderStatusChanged')) {
            return $data['status_label'] ?? 'Order status updated.';
        }
        if (str_contains($type, 'WelcomeNotification')) {
            return 'Your account is ready. Start shopping now.';
        }
        if (str_contains($type, 'AbandonedCartNotification')) {
            return $data['body'] ?? 'Items are waiting in your cart.';
        }
        if (str_contains($type, 'ReviewRequestNotification')) {
            $product = $data['product_name'] ?? null;
            return $product ? "How was your {$product}?" : 'Tell us about your purchase.';
        }

        return $data['body'] ?? 'You have a new notification.';
    }
}
