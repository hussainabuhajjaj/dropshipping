<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $table = 'support_messages';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_customer_id',
        'sender_user_id',
        'message_type',
        'body',
        'metadata',
        'is_internal_note',
        'read_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_internal_note' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function senderCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sender_customer_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}

