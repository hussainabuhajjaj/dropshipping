<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\MobileTranslation;

class TranslationsController extends ApiController
{
    private const MAX_TRANSLATION_KEY_LENGTH = 250;

    public function index(Request $request): JsonResponse
    {
        $supported = array_filter(array_map('trim', (array) config('services.translation_locales', ['en', 'fr'])));
        $supported = $supported ?: ['en', 'fr'];
        $requested = $request->query('locale');
        $requested = is_string($requested) ? strtolower(substr($requested, 0, 2)) : null;

        $locale = $requested && in_array($requested, $supported, true) ? $requested : app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        $fallbackMessages = $this->loadTranslations($fallback);
        $localeMessages = $locale === $fallback ? [] : $this->loadTranslations($locale);

        return $this->success([
            'locale' => $locale,
            'fallback' => $fallback,
            'translations' => array_merge($fallbackMessages, $localeMessages),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $locale = app()->getLocale();
        $keys = $request->input('keys', []);

        if (! is_array($keys)) {
            return $this->error('Invalid keys payload.', 422);
        }

        $uniqueKeys = collect($keys)
            ->filter(fn ($key) => is_string($key) && trim($key) !== '')
            ->map(fn ($key) => trim($key))
            ->filter(fn ($key) => mb_strlen($key) <= self::MAX_TRANSLATION_KEY_LENGTH)
            ->unique()
            ->take(300)
            ->values();

        if ($uniqueKeys->isNotEmpty()) {
            $now = now();
            $rows = $uniqueKeys
                ->map(fn (string $key): array => [
                    'locale' => $locale,
                    'key' => $key,
                    'value' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            MobileTranslation::query()->insertOrIgnore($rows);
        }

        return $this->success(['count' => $uniqueKeys->count()]);
    }

    private function loadTranslations(string $locale): array
    {
        $dbTranslations = MobileTranslation::query()
            ->where('locale', $locale)
            ->pluck('value', 'key')
            ->all();

        if (! empty($dbTranslations)) {
            return $dbTranslations;
        }

        $path = resource_path("lang/{$locale}.json");
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}
