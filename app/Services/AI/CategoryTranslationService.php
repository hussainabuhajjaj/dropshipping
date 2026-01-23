<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Products\Models\Category;
use App\Models\CategoryTranslation;

class CategoryTranslationService
{
    public function __construct(private TranslationProvider $client)
    {
    }

    /**
     * @param array<int, string> $locales
     */
    public function translate(Category $category, array $locales, string $sourceLocale = 'en', bool $force = false): void
    {
        $name = trim((string) $category->name);
        $description = trim((string) ($category->description ?? ''));
        $heroTitle = trim((string) ($category->hero_title ?? ''));
        $heroSubtitle = trim((string) ($category->hero_subtitle ?? ''));
        $heroCtaLabel = trim((string) ($category->hero_cta_label ?? ''));
        $metaTitle = trim((string) ($category->meta_title ?? ''));
        $metaDescription = trim((string) ($category->meta_description ?? ''));

        $category->loadMissing('translations');

        $apiKeyConfigured = (bool) (config('services.deepseek.key'));
        if (! $apiKeyConfigured) {
            logger()->warning('DeepSeek not configured, will only persist source locale for categories', [
                'category_id' => $category->id,
                'sourceLocale' => $sourceLocale,
                'locales' => $locales,
            ]);
        }

        foreach ($locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $existing = $category->translationForLocale($locale);
            $skipTranslation = ! $force && $existing && (
                $existing->name || $existing->description || $existing->hero_title || $existing->hero_subtitle
                || $existing->hero_cta_label || $existing->meta_title || $existing->meta_description
            );

            if ($locale === $sourceLocale) {
                if (! $skipTranslation) {
                    CategoryTranslation::updateOrCreate(
                        ['category_id' => $category->id, 'locale' => $locale],
                        [
                            'name' => $name,
                            'description' => $description,
                            'hero_title' => $heroTitle,
                            'hero_subtitle' => $heroSubtitle,
                            'hero_cta_label' => $heroCtaLabel,
                            'meta_title' => $metaTitle,
                            'meta_description' => $metaDescription,
                        ]
                    );
                }
                continue;
            }

            if (! $apiKeyConfigured || $skipTranslation) {
                continue;
            }

            $update = [];

            $update = array_merge($update, $this->translateField($name, 'name', $sourceLocale, $locale, $category));
            $update = array_merge($update, $this->translateField($description, 'description', $sourceLocale, $locale, $category));
            $update = array_merge($update, $this->translateField($heroTitle, 'hero_title', $sourceLocale, $locale, $category));
            $update = array_merge($update, $this->translateField($heroSubtitle, 'hero_subtitle', $sourceLocale, $locale, $category));
            $update = array_merge($update, $this->translateField($heroCtaLabel, 'hero_cta_label', $sourceLocale, $locale, $category));
            $update = array_merge($update, $this->translateField($metaTitle, 'meta_title', $sourceLocale, $locale, $category));
            $update = array_merge($update, $this->translateField($metaDescription, 'meta_description', $sourceLocale, $locale, $category));

            if ($update !== []) {
                CategoryTranslation::updateOrCreate(
                    ['category_id' => $category->id, 'locale' => $locale],
                    $update
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function translateField(string $value, string $field, string $source, string $target, Category $category): array
    {
        if ($value === '') {
            return [];
        }

        try {
            $candidate = trim((string) $this->client->translate($value, $source, $target));
            if ($this->isLikelySourceLanguage($candidate, $source, $target)) {
                logger()->warning('Category translation appears to be in source language, skipping', [
                    'category_id' => $category->id,
                    'locale' => $target,
                    'field' => $field,
                    'text' => substr($candidate, 0, 50),
                ]);
                return [];
            }

            return [$field => $candidate !== '' ? $candidate : $value];
        } catch (\Throwable $e) {
            logger()->error('Translation failed for category field', [
                'category_id' => $category->id,
                'locale' => $target,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    private function isLikelySourceLanguage(string $text, string $source, string $target): bool
    {
        if ($target === 'en' || $source === $target) {
            return false;
        }

        if ($target === 'fr') {
            $englishMarkers = ['the ', 'and ', 'or ', 'is ', 'are ', 'be ', 'have ', 'has '];
            $text = strtolower($text);
            $engCount = 0;
            $frenchMarkers = [' le ', ' la ', ' et ', ' ou ', ' est ', ' sont ', ' avoir ', ' a '];
            $frCount = 0;

            foreach ($englishMarkers as $marker) {
                $engCount += substr_count($text, $marker);
            }
            foreach ($frenchMarkers as $marker) {
                $frCount += substr_count($text, $marker);
            }

            return $engCount > 2 && $frCount === 0;
        }

        return false;
    }
}
