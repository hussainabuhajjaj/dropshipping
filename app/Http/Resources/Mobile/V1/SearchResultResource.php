<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResultResource extends JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        $products = data_get($this->resource, 'products');
        $categories = data_get($this->resource, 'categories');

        if ($products instanceof LengthAwarePaginator) {
            $products = $products->getCollection();
        }

        if ($products instanceof Collection) {
            $products = ProductResource::collection($products);
        }

        if ($categories instanceof Collection) {
            $categories = CategoryCardResource::collection($categories);
        }

        return [
            'query' => data_get($this->resource, 'query'),
            'products' => $products ?? [],
            'categories' => $categories ?? [],
        ];
    }
}

