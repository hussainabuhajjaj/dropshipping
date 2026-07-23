<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\V1\ApiController;
use App\Models\StorefrontBanner;
use Illuminate\Http\JsonResponse;

class StoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $locale = request()->header('X-Locale', 'en');

        $stories = StorefrontBanner::query()
            ->active()
            ->byDisplayType('story')
            ->orderBy('display_order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (StorefrontBanner $banner) use ($locale) {
                $imageUrl = $banner->image_path
                    ? (str_starts_with($banner->image_path, 'http')
                        ? $banner->image_path
                        : asset('storage/' . $banner->image_path))
                    : null;

                return [
                    'id' => $banner->id,
                    'title' => $banner->localizedValue('title', $locale) ?? $banner->title,
                    'description' => $banner->localizedValue('description', $locale) ?? $banner->description,
                    'image' => $imageUrl,
                    'badge_text' => $banner->localizedValue('badge_text', $locale) ?? $banner->badge_text,
                    'badge_color' => $banner->badge_color,
                    'background_color' => $banner->background_color,
                    'text_color' => $banner->text_color,
                    'cta_text' => $banner->localizedValue('cta_text', $locale) ?? $banner->cta_text,
                    'cta_url' => $banner->getCtaUrl(),
                    'target_type' => $banner->target_type,
                    'product_id' => $banner->product_id,
                    'category_id' => $banner->category_id,
                    'external_url' => $banner->external_url,
                    'story_type' => $banner->story_type,
                    'story_content' => $banner->getStoryContent(),
                ];
            });

        return $this->success($stories);
    }

    public function show(int $id): JsonResponse
    {
        $locale = request()->header('X-Locale', 'en');

        $banner = StorefrontBanner::query()
            ->active()
            ->byDisplayType('story')
            ->findOrFail($id);

        $imageUrl = $banner->image_path
            ? (str_starts_with($banner->image_path, 'http')
                ? $banner->image_path
                : asset('storage/' . $banner->image_path))
            : null;

        return $this->success([
            'id' => $banner->id,
            'title' => $banner->localizedValue('title', $locale) ?? $banner->title,
            'description' => $banner->localizedValue('description', $locale) ?? $banner->description,
            'image' => $imageUrl,
            'badge_text' => $banner->localizedValue('badge_text', $locale) ?? $banner->badge_text,
            'badge_color' => $banner->badge_color,
            'background_color' => $banner->background_color,
            'text_color' => $banner->text_color,
            'cta_text' => $banner->localizedValue('cta_text', $locale) ?? $banner->cta_text,
            'cta_url' => $banner->getCtaUrl(),
            'target_type' => $banner->target_type,
            'product_id' => $banner->product_id,
            'category_id' => $banner->category_id,
            'external_url' => $banner->external_url,
            'story_type' => $banner->story_type,
            'story_content' => $banner->getStoryContent(),
        ]);
    }
}
