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

        // Ensure variants are available for translation
        $product->loadMissing('variants');

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
            $skipProductTranslation = ! $force && $existing && ($existing->name || $existing->description);

            if ($locale === $sourceLocale) {
                if (! $skipProductTranslation) {
                    ProductTranslation::updateOrCreate(
                        ['product_id' => $product->id, 'locale' => $locale],
                        ['name' => $name, 'description' => $description]
                    );
                }

                $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $apiKeyConfigured, $product);
                continue;
            }

            if (! $apiKeyConfigured) {
                // Skip translating to other locales when provider is not available
                $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $apiKeyConfigured, $product);
                continue;
            }

            if ($skipProductTranslation) {
                $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $apiKeyConfigured, $product);
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

            // Persist translated content when successful
            if (! $translationFailed) {
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

            $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $apiKeyConfigured, $product);
        }
    }

    /**
     * Translate variant titles and store per-locale copies inside metadata.
     *
     * @param iterable<int, mixed> $variants
     */
    private function translateVariants(iterable $variants, string $locale, string $sourceLocale, bool $force, bool $apiKeyConfigured, Product $product): void
    {
        foreach ($variants as $variant) {
            $title = trim((string) ($variant->title ?? ''));
            if ($title === '') {
                continue;
            }

            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translations = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];
            $existing = $translations[$locale]['title'] ?? null;

            if (! $force && $existing) {
                continue;
            }

            if ($locale === $sourceLocale) {
                $translations[$locale]['title'] = $title;
                $metadata['translations'] = $translations;
                $variant->metadata = $metadata;
                $variant->save();
                continue;
            }

            if (! $apiKeyConfigured) {
                continue;
            }

            try {
                $candidate = trim((string) $this->client->translate($title, $sourceLocale, $locale));

                if ($this->isLikelySourceLanguage($candidate, $sourceLocale, $locale)) {
                    logger()->warning('Variant translation appears to be in source language, skipping', [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id ?? null,
                        'locale' => $locale,
                        'text' => substr($candidate, 0, 50),
                    ]);
                    continue;
                }

                $translations[$locale]['title'] = $candidate !== '' ? $candidate : $title;
                $metadata['translations'] = $translations;
                $variant->metadata = $metadata;
                $variant->save();
            } catch (\Throwable $e) {
                logger()->error('Translation failed for product variant title', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id ?? null,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
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
