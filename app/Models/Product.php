<?php

namespace App\Models;

use App\Domain\Products\Models\Product as DomainProduct;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends DomainProduct
{
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->newQuery();

        if (is_numeric($value)) {
            $model = $query->whereKey($value)->first();
            if ($model) {
                return $model;
            }
        }

        $field = $field ?: 'slug';

        return $query->where($field, $value)->first();
    }
}
