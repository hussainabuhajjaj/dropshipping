<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'total_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'summary',
    ];

    protected $casts = [
        'summary' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
