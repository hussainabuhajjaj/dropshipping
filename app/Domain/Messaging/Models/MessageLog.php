<?php

namespace App\Domain\Messaging\Models;

use App\Enums\MessageChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $table = 'message_logs';

    protected $fillable = [
        'message_template_id',
        'order_id',
        'shipment_id',
        'customer_id',
        'recipient',
        'channel',
        'subject',
        'message_content',
        'placeholders_used',
        'status',
        'error_message',
        'external_message_id',
        'sent_at',
        'opened_at',
        'clicked_at',
        'sent_by',
        'is_automatic',
    ];

    protected $casts = [
        'channel' => MessageChannel::class,
        'placeholders_used' => 'array',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'is_automatic' => 'boolean',
    ];

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Orders\Models\Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Orders\Models\Shipment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sent_by');
    }

    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'opened', 'clicked']);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'bounced']);
    }

    public function isOpened(): bool
    {
        return !is_null($this->opened_at);
    }

    public function wasClicked(): bool
    {
        return !is_null($this->clicked_at);
    }

    public function getStatusBadgeColor(): string
    {
        return match ($this->status) {
            'sent', 'opened', 'clicked' => 'success',
            'queued', 'sending' => 'info',
            'failed', 'bounced' => 'danger',
            default => 'gray',
        };
    }
}
