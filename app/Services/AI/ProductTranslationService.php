<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Products\Models\Product;
use App\Models\ProductTranslation;

class ProductTranslationService
{
    public function __construct(private TranslationProvider $client)
    {
    }

    /**
     * @param array<int, string> $locales
     */
    public function translate(Product $product, array $locales, string $sourceLocale = 'en', bool $force = false): void
    {
        $name = trim((string) $product->name);
        $description = trim((string) ($product->description ?? ''));

        $apiKeyConfigured = (bool) (config('services.deepseek.key'));
        if (! $apiKeyConfigured) {
            logger()->warning('DeepSeek not configured, will only persist source locale', [
                'product_id' => $product->id,
                'sourceLocale' => $sourceLocale,
                'locales' => $locales,
            ]);
        }

        foreach ($locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $existing = $product->translationForLocale($locale);
            if (! $force && $existing && ($existing->name || $existing->description)) {
                continue;
            }

            if ($locale === $sourceLocale) {
                ProductTranslation::updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    ['name' => $name, 'description' => $description]
                );
                continue;
            }

            if (! $apiKeyConfigured) {
                // Skip translating to other locales when provider is not available
                continue;
            }

            $translatedName = null;
            $translatedDescription = null;
            $translationFailed = false;

            try {
                if ($name !== '') {
                    $candidate = trim((string) $this->client->translate($name, $sourceLocale, $locale));
                    // Detect if response is still in source language (sanity check for en→fr)
                    if ($this->isLikelySourceLanguage($candidate, $sourceLocale, $locale)) {
                        logger()->warning('Translation appears to be in source language, skipping', [
                            'product_id' => $product->id,
                            'locale' => $locale,
                            'text' => substr($candidate, 0, 50),
                        ]);
                        $translationFailed = true;
                    } else {
                        $translatedName = $candidate !== '' ? $candidate : $name;
                    }
                }
            } catch (\Throwable $e) {
                logger()->error('Translation failed for product name', [
                    'product_id' => $product->id,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
                $translationFailed = true;
            }

            try {
                if ($description !== '' && ! $translationFailed) {
                    $candidate = trim((string) $this->client->translate($description, $sourceLocale, $locale));
                    if ($this->isLikelySourceLanguage($candidate, $sourceLocale, $locale)) {
                        logger()->warning('Translation appears to be in source language, skipping', [
                            'product_id' => $product->id,
                            'locale' => $locale,
                            'text' => substr($candidate, 0, 50),
                        ]);
                        $translationFailed = true;
                    } else {
                        $translatedDescription = $candidate !== '' ? $candidate : $description;
                    }
                }
            } catch (\Throwable $e) {
                logger()->error('Translation failed for product description', [
                    'product_id' => $product->id,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
                $translationFailed = true;
            }

            // If translation failed, skip saving to avoid corrupting target locale with source
            if ($translationFailed) {
                continue;
            }

            // Persist translated content
            $update = [];
            if ($translatedName !== null) {
                $update['name'] = $translatedName;
            }
            if ($translatedDescription !== null) {
                $update['description'] = $translatedDescription;
            }

            if ($update !== []) {
                ProductTranslation::updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    $update
                );
            }
        }
    }

    private function isLikelySourceLanguage(string $text, string $source, string $target): bool
    {
        // Simple heuristic: check for common English words if target is not English
        if ($target === 'en' || $source === $target) {
            return false;
        }

        if ($target === 'fr') {
            // Check for common English articles/words that shouldn't appear if properly translated to French
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

            // If mostly English markers and no French markers, likely still English
            return $engCount > 2 && $frCount === 0;
        }

        return false;
    }
}
