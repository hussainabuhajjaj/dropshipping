<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CjProductSnapshot extends Model
{
    protected $fillable = [
        'pid',
        'name',
        'sku',
        'category_id',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}
