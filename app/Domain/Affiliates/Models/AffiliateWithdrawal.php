<?php

namespace App\Domain\Affiliates\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateWithdrawal extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'amount',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal',
        'processed_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
