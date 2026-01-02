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

    public static function getLatestToken(): ?self
    {
        return self::latest()->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canRefresh(): bool
    {
        return $this->refresh_token && 
               $this->refresh_expires_at && 
               $this->refresh_expires_at->isFuture();
    }
}
