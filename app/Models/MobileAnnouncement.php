<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileAnnouncement extends Model
{
    protected $fillable = [
        'locale',
        'enabled',
        'title',
        'body',
        'image',
        'action_href',
        'send_database',
        'send_push',
        'send_email',
        'notified_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'send_database' => 'boolean',
        'send_push' => 'boolean',
        'send_email' => 'boolean',
        'notified_at' => 'datetime',
    ];
}

