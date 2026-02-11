<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Marketing\MarketingContentDraftCreated;
use App\Models\User;

class MarketingContentDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_type',
        'target_id',
        'locale',
        'channel',
        'generated_fields',
        'prompt_context',
        'status',
        'requested_by',
        'approved_by',
        'rejected_reason',
    ];

    protected $casts = [
        'generated_fields' => 'array',
        'prompt_context' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function booted(): void
    {
        static::created(function (self $draft): void {
            $admins = User::query()->where('role', 'admin')->get();
            if ($admins->isEmpty()) {
                return;
            }
            Notification::send($admins, new MarketingContentDraftCreated($draft));
        });
    }
}
