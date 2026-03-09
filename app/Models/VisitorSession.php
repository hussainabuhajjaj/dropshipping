<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorSession extends Model
{
    protected $fillable = [
        'channel',
        'visitor_key',
        'customer_id',
        'user_id',
        'session_id',
        'locale',
        'platform',
        'ip_address',
        'user_agent',
        'started_at',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(VisitorEvent::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
