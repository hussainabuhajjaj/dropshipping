<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Http\Request;

class AnnouncementResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        $homeBuilder = app(HomeBuilderService::class);

        return [
            'id' => (int) $this->id,
            'locale' => $this->locale,
            'title' => $this->title,
            'body' => $this->body,
            'image' => $homeBuilder->normalizeImage($this->image),
            'actionHref' => $this->action_href,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

