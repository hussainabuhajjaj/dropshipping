<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->resource['currency'] ?? 'USD',
            'hero' => $this->resource['hero'] ?? [],
            'categories' => CategoryCardResource::collection($this->resource['categories'] ?? []),
            'flashDeals' => ProductResource::collection($this->resource['flashDeals'] ?? []),
            'trending' => ProductResource::collection($this->resource['trending'] ?? []),
            'recommended' => ProductResource::collection($this->resource['recommended'] ?? []),
            'topStrip' => $this->resource['topStrip'] ?? [],
            'valueProps' => $this->resource['valueProps'] ?? [],
            'seasonalDrops' => $this->resource['seasonalDrops'] ?? [],
            'banners' => $this->resource['banners'] ?? [],
            'newsletterPopup' => $this->resource['newsletterPopup'] ?? null,
            'storefront' => $this->resource['storefront'] ?? null,
        ];
    }
}
