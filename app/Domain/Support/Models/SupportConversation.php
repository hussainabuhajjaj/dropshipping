<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasFactory;

    protected $table = 'support_conversations';

    protected $fillable = [
        'uuid',
        'customer_id',
        'assigned_user_id',
        'channel',
        'status',
        'requested_agent',
        'active_agent',
        'ai_enabled',
        'handoff_requested',
        'priority',
        'topic',
        'tags',
        'context',
        'last_message_at',
        'last_customer_message_at',
        'last_agent_message_at',
        'resolved_at',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'handoff_requested' => 'boolean',
        'tags' => 'array',
        'context' => 'array',
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_agent_message_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function scopeOverdueSla(Builder $query, CarbonInterface $threshold): Builder
    {
        return $query
            ->where('status', 'pending_agent')
            ->where(function (Builder $builder) use ($threshold): void {
                $builder
                    ->where(function (Builder $inner) use ($threshold): void {
                        $inner
                            ->whereNotNull('last_customer_message_at')
                            ->where('last_customer_message_at', '<=', $threshold);
                    })
                    ->orWhere(function (Builder $inner) use ($threshold): void {
                        $inner
                            ->whereNull('last_customer_message_at')
                            ->whereNotNull('last_message_at')
                            ->where('last_message_at', '<=', $threshold);
                    })
                    ->orWhere(function (Builder $inner) use ($threshold): void {
                        $inner
                            ->whereNull('last_customer_message_at')
                            ->whereNull('last_message_at')
                            ->where('created_at', '<=', $threshold);
                    });
            });
    }
}
