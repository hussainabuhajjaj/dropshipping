<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class WooCommerceSyncLog extends Model
{
    protected $table = 'woocommerce_sync_logs';

    protected $fillable = [
        'type',
        'entity_type',
        'entity_id',
        'action',
        'status',
        'request_summary',
        'response_summary',
        'error',
        'meta',
        'completed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];
}
