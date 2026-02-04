<?php

declare(strict_types=1);

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection as BaseCollection;

class ResourceCollection extends BaseCollection
{
    public function __construct($resource, ?string $collects = null)
    {
        parent::__construct($resource);
        if ($collects) {
            $this->collects = $collects;
        }
    }
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $default['meta']['current_page'],
                'from' => $default['meta']['from'],
                'last_page' => $default['meta']['last_page'],
                'per_page' => $default['meta']['per_page'],
                'to' => $default['meta']['to'],
                'total' => $default['meta']['total'],
            ],
            'links' => [
                'first' => $default['links']['first'],
                'last' => $default['links']['last'],
                'prev' => $default['links']['prev'],
                'next' => $default['links']['next'],
            ],
        ];
    }
}
