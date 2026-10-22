<?php

namespace App\Domain\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTriggerHistory extends Model
{
    protected $table = 'message_trigger_history';

    protected $fillable = [
        'message_template_id',
        'order_id',
        'shipment_id',
        'trigger_type',
        'trigger_data',
        'status',
        'scheduled_for',
        'sent_at',
        'cancellation_reason',
        'message_log_id',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
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

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(MessageLog::class, 'message_log_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['sent', 'failed', 'cancelled']);
    }
}
