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
        $locale = app()->getLocale();
        $hasTranslations = method_exists($this->resource, 'translatedValue');
        $name = $hasTranslations ? $this->resource->translatedValue('name', $locale) : data_get($this->resource, 'name');
        $description = $hasTranslations
            ? $this->resource->translatedValue('description', $locale)
            : data_get($this->resource, 'description');
        $heroTitle = $hasTranslations
            ? $this->resource->translatedValue('hero_title', $locale)
            : data_get($this->resource, 'hero_title');
        $heroSubtitle = $hasTranslations
            ? $this->resource->translatedValue('hero_subtitle', $locale)
            : data_get($this->resource, 'hero_subtitle');
        $heroCtaLabel = $hasTranslations
            ? $this->resource->translatedValue('hero_cta_label', $locale)
            : data_get($this->resource, 'hero_cta_label');
        $metaTitle = $hasTranslations
            ? $this->resource->translatedValue('meta_title', $locale)
            : data_get($this->resource, 'meta_title');
        $metaDescription = $hasTranslations
            ? $this->resource->translatedValue('meta_description', $locale)
            : data_get($this->resource, 'meta_description');

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
            'name' => $name,
            'slug' => data_get($this->resource, 'slug'),
            'description' => $description,
            'heroTitle' => $heroTitle,
            'heroSubtitle' => $heroSubtitle,
            'heroImage' => $heroImage,
            'heroCtaLabel' => $heroCtaLabel,
            'heroCtaLink' => data_get($this->resource, 'hero_cta_link'),
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
        ];
    }
}
