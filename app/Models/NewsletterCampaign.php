<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class NewsletterCampaign extends Model
{
    protected $fillable = [
        'subject',
        'body_markdown',
        'action_url',
        'action_label',
        'status',
        'total_subscribers',
        'sent_by',
        'sent_at',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
        'body_markdown' => 'array',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(NewsletterCampaignLog::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
