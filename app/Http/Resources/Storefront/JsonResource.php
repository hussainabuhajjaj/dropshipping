<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as BaseResource;

class JsonResource extends BaseResource
{
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
