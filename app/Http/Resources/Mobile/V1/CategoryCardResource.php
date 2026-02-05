<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Request;

class CategoryCardResource extends \App\Http\Resources\Storefront\CategoryCardResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        $data = parent::toArray($request);
        $children = $this->resource->relationLoaded('children')
            ? $this->resource->children
            : null;

        if ($children && $children->count() > 0) {
            $data['children'] = self::collection($children)->toArray($request);
        }
        return $data;
    }
}
