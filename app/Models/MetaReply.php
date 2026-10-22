<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetaReplyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaReply extends Model
{
    use HasFactory;

    protected $table = 'meta_replies';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => MetaReplyStatus::class,
            'auto_send' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MetaInboxMessage::class, 'message_id');
    }

    public function approve(?int $userId = null): void
    {
        $this->status = MetaReplyStatus::Approved;
        $this->approved_by = $userId;
        $this->save();
    }
}
