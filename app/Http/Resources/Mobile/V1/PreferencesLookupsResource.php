<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Request;

class PreferencesLookupsResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'countries' => $data['countries'] ?? [],
            'currencies' => $data['currencies'] ?? [],
            'sizes' => $data['sizes'] ?? [],
            'languages' => $data['languages'] ?? [],
        ];
    }
}
