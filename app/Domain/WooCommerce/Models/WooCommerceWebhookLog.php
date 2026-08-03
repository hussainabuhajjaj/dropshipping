<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class WooCommerceWebhookLog extends Model
{
    protected $table = 'woocommerce_webhook_logs';

    protected $fillable = [
        'webhook_id',
        'delivery_id',
        'event_type',
        'resource',
        'event',
        'payload',
        'status',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
