<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use App\Services\Notifications\NotificationPresenter;
use Illuminate\Http\Request;

class NotificationResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        $presenter = app(NotificationPresenter::class);
        $payload = $presenter->format($this->resource);

        return [
            'id' => $payload['id'] ?? (string) $this->id,
            'type' => $payload['type'] ?? (string) $this->type,
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
            'action_url' => $payload['action_url'] ?? null,
            'action_label' => $payload['action_label'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
