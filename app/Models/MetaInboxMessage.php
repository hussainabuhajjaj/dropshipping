<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetaReplyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MetaInboxMessage extends Model
{
    use HasFactory;

    protected $table = 'meta_inbox_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function reply(): HasOne
    {
        return $this->hasOne(MetaReply::class, 'message_id');
    }

    public function scopeNeedsReply($query)
    {
        return $query->whereDoesntHave('reply');
    }
}
