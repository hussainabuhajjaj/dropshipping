<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as BaseResource;

class JsonResource extends BaseResource
{
    /**
     * Customize the response for a request.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-API-Version', 'v1');
    }
}
