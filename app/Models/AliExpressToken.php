<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliExpressToken extends Model
{
    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
        'refresh_expires_at',
        'raw',
    ];
    protected $casts = [
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'raw' => 'array',
    ];
}
