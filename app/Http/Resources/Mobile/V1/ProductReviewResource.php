<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;

use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewResource extends \App\Http\Resources\Storefront\JsonResource
{
    use WithoutSuccessWrapper;
    public function toArray(Request $request): array
    {
        /** @var ProductReview $review */
        $review = $this->resource;
        $externalPayload = is_array($review->external_payload ?? null) ? $review->external_payload : [];
        $author = $review->customer?->name
            ?? ($externalPayload['commentUser'] ?? $externalPayload['nickname'] ?? 'Verified buyer');

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'images' => $review->images ?? [],
            'verified_purchase' => (bool) $review->verified_purchase,
            'helpful_count' => (int) ($review->helpful_count ?? 0),
            'status' => $review->status,
            'author' => $author,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
