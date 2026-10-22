<?php

namespace App\Domain\Messaging\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageTemplateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    protected $table = 'message_templates';

    protected $fillable = [
        'name',
        'title',
        'type',
        'subject',
        'message',
        'description',
        'required_placeholders',
        'available_placeholders',
        'default_channel',
        'enabled_channels',
        'is_active',
        'send_automatically',
        'trigger_types',
        'auto_send_delay_hours',
        'condition_rules',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => MessageTemplateType::class,
        'default_channel' => MessageChannel::class,
        'required_placeholders' => 'array',
        'available_placeholders' => 'array',
        'enabled_channels' => 'array',
        'trigger_types' => 'array',
        'is_active' => 'boolean',
        'send_automatically' => 'boolean',
    ];

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'message_template_id');
    }

    public function triggerHistory(): HasMany
    {
        return $this->hasMany(MessageTriggerHistory::class, 'message_template_id');
    }

    public function getAvailablePlaceholders(): array
    {
        return $this->available_placeholders ?? [
            'order_number',
            'order_id',
            'customer_name',
            'tracking_number',
            'carrier',
            'estimated_delivery',
            'refund_amount',
            'exception_type',
        ];
    }

    public function getRequiredPlaceholders(): array
    {
        return $this->required_placeholders ?? [];
    }

    public function hasAllRequiredPlaceholders(array $placeholders): bool
    {
        $required = $this->getRequiredPlaceholders();
        return empty(array_diff($required, array_keys($placeholders)));
    }

    public function fillPlaceholders(array $placeholders): string
    {
        $message = $this->message;

        foreach ($placeholders as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }

    public function getTriggerTypes(): array
    {
        return $this->trigger_types ?? [];
    }

    public function hasAutoSend(): bool
    {
        return $this->send_automatically && !empty($this->getTriggerTypes());
    }
}
