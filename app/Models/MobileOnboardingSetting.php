<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileOnboardingSetting extends Model
{
    protected $fillable = [
        'locale',
        'enabled',
        'slides',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'slides' => 'array',
    ];
}

