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
        
        // Fallback: if no hero_image, try to get first product image from this category or its children
        if (!$heroImageRaw && method_exists($this->resource, 'getAttribute')) {
            $categoryId = $this->resource->getAttribute('id');
            if ($categoryId) {
                // First try direct products
                $firstProduct = \App\Models\Product::where('category_id', $categoryId)
                    ->where('is_active', true)
                    ->with('images')
                    ->first();
                
                // If no direct products, try products from child categories
                if (!$firstProduct || $firstProduct->images->isEmpty()) {
                    $childCategoryIds = \App\Models\Category::where('parent_id', $categoryId)
                        ->where('is_active', true)
                        ->pluck('id');
                    
                    if ($childCategoryIds->isNotEmpty()) {
                        $firstProduct = \App\Models\Product::whereIn('category_id', $childCategoryIds)
                            ->where('is_active', true)
                            ->with('images')
                            ->first();
                    }
                }
                
                if ($firstProduct && $firstProduct->images->isNotEmpty()) {
                    $heroImageRaw = $firstProduct->images->first()->url;
                }
            }
        }
        
        // Use hero_image as the primary image since there's no separate image column
        $image = $this->resolveImage($heroImageRaw);
        $heroImage = $this->resolveImage($heroImageRaw);
        $locale = app()->getLocale();
        $name = method_exists($this->resource, 'translatedValue')
            ? $this->resource->translatedValue('name', $locale)
            : data_get($this->resource, 'name');
        $rawPreviews = data_get($this->resource, 'subcategory_previews', []);
        $previews = collect($rawPreviews)->map(function ($preview) {
            $image = $this->resolveImage(data_get($preview, 'image_url'));

            return [
                'id' => data_get($preview, 'id'),
                'name' => data_get($preview, 'name'),
                'slug' => data_get($preview, 'slug'),
                'image_url' => $image,
            ];
        })->values()->all();
        $productCount = (int) (
            data_get(
                $this->resource,
                'product_count',
                data_get($this->resource, 'count', data_get($this->resource, 'products_count', 0))
            ) ?? 0
        );

        // Get children categories if they're loaded
        $children = [];
        if ($this->resource->relationLoaded('children')) {
            $children = static::collection($this->resource->children);
        }

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => $name,
            'slug' => data_get($this->resource, 'slug'),
            'count' => $productCount,
            'product_count' => $productCount,
            'image' => $image,
            'heroImage' => $heroImage,
            'accent' => data_get($this->resource, 'accent'),
            'subcategory_previews' => $previews,
            'children' => $children,
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
