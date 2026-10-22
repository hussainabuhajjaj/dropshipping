<?php

declare(strict_types=1);

namespace App\Domain\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'url',
        'position',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
