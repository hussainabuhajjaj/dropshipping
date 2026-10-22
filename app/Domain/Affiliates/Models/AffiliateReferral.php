<?php

namespace App\Domain\Affiliates\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferral extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'visitor_token',
        'user_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
