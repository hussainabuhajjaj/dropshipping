<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $image = data_get($this->resource, 'image', data_get($this->resource, 'hero_image'));

        if (
            $image
            && ! str_starts_with($image, 'http://')
            && ! str_starts_with($image, 'https://')
            && ! str_starts_with($image, '/storage/')
            && ! str_starts_with($image, 'storage/')
        ) {
            $image = Storage::url($image);
        }

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'slug' => data_get($this->resource, 'slug'),
            'count' => (int) (data_get($this->resource, 'count', data_get($this->resource, 'products_count', 0)) ?? 0),
            'image' => $image,
            'accent' => data_get($this->resource, 'accent'),
        ];
    }
}
