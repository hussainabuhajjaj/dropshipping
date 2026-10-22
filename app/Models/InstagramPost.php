<?php

namespace App\Models;

use App\Domain\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramPost extends Model
{
    protected $fillable = [
        'product_id',
        'posted_date',
        'day_rank',
        'category_slug',
        'image_url',
        'caption',
        'hashtags',
        'quality_score',
    ];

    protected $casts = [
        'posted_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}