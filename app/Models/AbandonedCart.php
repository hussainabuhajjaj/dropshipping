<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCart extends Model
{
    protected $fillable = [
        'session_id',
        'customer_id',
        'email',
        'cart_data',
        'abandoned_at',
        'last_activity_at',
        'reminder_sent_at',
        'recovered_at',
    ];

    protected $casts = [
        'cart_data' => 'array',
        'abandoned_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
