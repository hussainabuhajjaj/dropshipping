<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliExpressToken extends Model
{

    protected $table = 'aliexpress_tokens';
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


    public function isExpired()
    {
        return $this->expires_at && \Carbon\Carbon::parse($this->expires_at)->isPast();
    }
}
