<?php

namespace App\Models;

use App\Domain\Products\Models\Category as DomainCategory;

class Category extends DomainCategory
{
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
