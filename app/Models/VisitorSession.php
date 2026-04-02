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
        'source_type',
        'source_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'device_type',
        'browser_family',
        'os_family',
        'ip_address',
        'user_agent',
        'landing_route_name',
        'landing_path',
        'landing_page_key',
        'last_route_name',
        'last_path',
        'last_page_key',
        'hits_count',
        'started_at',
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'hits_count' => 'int',
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
