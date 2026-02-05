<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $heroImageRaw = data_get($this->resource, 'hero_image');
        $image = data_get($this->resource, 'image', $heroImageRaw);
        $image = $this->resolveImage($image);
        $heroImage = $this->resolveImage($heroImageRaw);
        $locale = app()->getLocale();
        $name = method_exists($this->resource, 'translatedValue')
            ? $this->resource->translatedValue('name', $locale)
            : data_get($this->resource, 'name');

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => $name,
            'slug' => data_get($this->resource, 'slug'),
            'count' => (int) (data_get($this->resource, 'count', data_get($this->resource, 'products_count', 0)) ?? 0),
            'image' => $image,
            'heroImage' => $heroImage,
            'accent' => data_get($this->resource, 'accent'),
        ];
    }

    private function resolveImage(?string $image): ?string
    {
        if (
            $image
            && ! str_starts_with($image, 'http://')
            && ! str_starts_with($image, 'https://')
            && ! str_starts_with($image, '/storage/')
            && ! str_starts_with($image, 'storage/')
        ) {
            return url(Storage::url($image));
        }

        return $image;
    }
}
