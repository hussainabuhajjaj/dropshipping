<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsletterSubscriptionResource extends JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        return [
            'subscriber_id' => $this->resource['subscriber_id'] ?? $this->resource['id'] ?? null,
            'email' => $this->resource['email'] ?? null,
            'message' => $this->resource['message'] ?? 'Thanks for subscribing!',
        ];
    }
}
