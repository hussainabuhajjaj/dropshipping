<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CJWebhookLog extends Model
{
    protected $table = 'cj_webhook_logs';

    protected $fillable = [
        'message_id',
        'request_id',
        'type',
        'message_type',
        'payload',
        'attempts',
        'processed',
        'processed_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
