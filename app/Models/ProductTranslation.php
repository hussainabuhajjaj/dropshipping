<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'description',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
