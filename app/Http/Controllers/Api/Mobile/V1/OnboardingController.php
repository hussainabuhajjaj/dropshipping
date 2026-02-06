<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\MobileOnboardingSetting;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends ApiController
{
    public function index(HomeBuilderService $homeBuilder): JsonResponse
    {
        $locale = app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        $setting = MobileOnboardingSetting::query()
            ->where('locale', $locale)
            ->first();

        if (! $setting && $fallback !== $locale) {
            $setting = MobileOnboardingSetting::query()
                ->where('locale', $fallback)
                ->first();
        }

        if (! $setting) {
            return $this->success([
                'configured' => false,
                'enabled' => true,
                'locale' => $locale,
                'slides' => [],
            ]);
        }

        $rawSlides = $setting->slides;
        $rawSlides = is_array($rawSlides) ? $rawSlides : [];

        $slides = collect($rawSlides)->map(function ($slide, int $index) use ($homeBuilder) {
            if (! is_array($slide)) {
                return null;
            }

            $key = isset($slide['key']) && is_string($slide['key']) && trim($slide['key']) !== ''
                ? trim($slide['key'])
                : 'slide-' . $index;

            $background = isset($slide['background']) && is_string($slide['background']) ? $slide['background'] : 'hello';
            if (! in_array($background, ['hello', 'ready'], true)) {
                $background = 'hello';
            }

            $color1 = isset($slide['image_color_1']) && is_string($slide['image_color_1'])
                ? $slide['image_color_1']
                : '#ffcad9';
            $color2 = isset($slide['image_color_2']) && is_string($slide['image_color_2'])
                ? $slide['image_color_2']
                : '#f39db0';

            $imagePath = isset($slide['image']) && is_string($slide['image']) && trim($slide['image']) !== ''
                ? trim($slide['image'])
                : null;

            $image = $homeBuilder->normalizeImage($imagePath);

            $actionHref = isset($slide['action_href']) && is_string($slide['action_href']) && trim($slide['action_href']) !== ''
                ? trim($slide['action_href'])
                : null;

            return [
                'key' => $key,
                'background' => $background,
                'title' => isset($slide['title']) && is_string($slide['title']) ? $slide['title'] : '',
                'body' => isset($slide['body']) && is_string($slide['body']) ? $slide['body'] : '',
                'image' => $image,
                'imageColors' => [$color1, $color2],
                'actionHref' => $actionHref,
            ];
        })
            ->filter()
            ->values()
            ->all();

        return $this->success([
            'configured' => true,
            'enabled' => (bool) $setting->enabled,
            'locale' => $setting->locale,
            'updatedAt' => optional($setting->updated_at)->toISOString(),
            'slides' => $slides,
        ]);
    }
}
