<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $heroImage = $this->resolveImage($this->hero_image);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'hero_title' => $this->hero_title,
            'hero_subtitle' => $this->hero_subtitle,
            'hero_image' => $heroImage,
            'hero_cta_label' => $this->hero_cta_label,
            'hero_cta_link' => $this->hero_cta_link,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'parent_id' => $this->parent_id,
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->when($this->products_count !== null, $this->products_count),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
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
