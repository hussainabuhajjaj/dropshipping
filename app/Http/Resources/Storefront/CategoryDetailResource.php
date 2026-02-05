<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $heroImage = data_get($this->resource, 'hero_image');

        if (
            $heroImage
            && ! str_starts_with($heroImage, 'http://')
            && ! str_starts_with($heroImage, 'https://')
            && ! str_starts_with($heroImage, '/storage/')
            && ! str_starts_with($heroImage, 'storage/')
        ) {
            $heroImage = url(Storage::url($heroImage));
        }

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'slug' => data_get($this->resource, 'slug'),
            'description' => data_get($this->resource, 'description'),
            'heroTitle' => data_get($this->resource, 'hero_title'),
            'heroSubtitle' => data_get($this->resource, 'hero_subtitle'),
            'heroImage' => $heroImage,
            'heroCtaLabel' => data_get($this->resource, 'hero_cta_label'),
            'heroCtaLink' => data_get($this->resource, 'hero_cta_link'),
            'metaTitle' => data_get($this->resource, 'meta_title'),
            'metaDescription' => data_get($this->resource, 'meta_description'),
        ];
    }
}
