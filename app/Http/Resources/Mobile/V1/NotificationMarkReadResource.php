<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use Illuminate\Http\Request;

class NotificationMarkReadResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'ok' => (bool) ($data['ok'] ?? false),
            'read_ids' => array_values($data['read_ids'] ?? []),
            'unread_count' => isset($data['unread_count']) ? (int) $data['unread_count'] : 0,
        ];
    }
}
