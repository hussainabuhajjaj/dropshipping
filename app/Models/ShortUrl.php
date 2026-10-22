<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortUrl extends Model
{
    protected $fillable = [
        'code',
        'original_url',
        'product_id',
        'clicks',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }
}
