<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'channel',
        'is_enabled',
        'subject',
        'body',
        'sender_name',
        'sender_email',
        'variables',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'variables' => 'array',
    ];

    public static function getTemplateKeys(): array
    {
        return [
            'order_placed' => 'Order Placed',
            'order_confirmed' => 'Order Confirmed',
            'order_shipped' => 'Order Shipped',
            'order_delivered' => 'Order Delivered',
            'order_cancelled' => 'Order Cancelled',
            'payment_received' => 'Payment Received',
            'refund_issued' => 'Refund Issued',
            'abandoned_checkout' => 'Abandoned Checkout Recovery',
            'review_requested' => 'Review Requested',
            'low_stock_alert' => 'Low Stock Alert',
        ];
    }

    public static function getChannels(): array
    {
        return [
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push Notification',
        ];
    }

    /**
     * Get available template variables based on template key.
     */
    public function getAvailableVariables(): array
    {
        return match ($this->key) {
            'order_confirmed' => ['order_number', 'customer_name', 'total', 'order_date', 'items'],
            'order_shipped' => ['order_number', 'customer_name', 'tracking_number', 'carrier', 'items'],
            'order_delivered' => ['order_number', 'customer_name', 'delivery_date'],
            'abandoned_checkout' => ['customer_name', 'cart_items', 'cart_total', 'checkout_url'],
            'payment_failed' => ['order_number', 'customer_name', 'failure_reason'],
            'refund_processed' => ['order_number', 'customer_name', 'refund_amount', 'refund_reason'],
            default => ['customer_name'],
        };
    }
}
