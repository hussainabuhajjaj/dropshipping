<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryShowResource extends JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $category = $this->resource['category'] ?? null;
        $products = $this->resource['products'] ?? null;

        $pagination = null;
        if ($products instanceof LengthAwarePaginator) {
            $pagination = [
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
                'perPage' => $products->perPage(),
                'total' => $products->total(),
            ];
        }

        return [
            'category' => $category ? new CategoryDetailResource($category) : null,
            'products' => $products instanceof LengthAwarePaginator
                ? ProductResource::collection($products->getCollection())
                : ProductResource::collection($products ?? []),
            'pagination' => $pagination,
        ];
    }
}
