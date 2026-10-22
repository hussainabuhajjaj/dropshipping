<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Products\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductTranslationService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const BATCH_SIZE = 12; // Fewer requests while staying reliable
    private const BATCH_CHAR_BUDGET = 12000; // Keep prompts bounded to reduce retries
    private const MAX_TEXT_LENGTH = 6000; // DeepSeek context limit

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

        $providerConfigured = $this->isProviderConfigured();
        if (! $providerConfigured) {
            Log::warning('Translation provider not configured, will only persist source locale', [
                'product_id' => $product->id,
                'sourceLocale' => $sourceLocale,
                'locales' => $locales,
                'provider' => config('services.translation_provider'),
            ]);
        }

        foreach ($locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            $existing = $product->translationForLocale($locale);
            $skipProductTranslation = ! $force && $existing && ($existing->name || $existing->description);

            // ENHANCED: Better skipping logic with logging
            if ($skipProductTranslation) {
                Log::info('Skipping product translation - already exists', [
                    'product_id' => $product->id,
                    'locale' => $locale,
                    'has_name' => !empty($existing->name),
                    'has_description' => !empty($existing->description),
                    'force' => $force,
                ]);
                
                // Still translate variants if needed
                $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $providerConfigured, $product);
                continue;
            }

            if ($locale === $sourceLocale) {
                if (! $skipProductTranslation) {
                    ProductTranslation::updateOrCreate(
                        ['product_id' => $product->id, 'locale' => $locale],
                        ['name' => $name, 'description' => $description]
                    );
                    
                    Log::info('Source locale translation saved', [
                        'product_id' => $product->id,
                        'locale' => $locale,
                        'name_length' => strlen($name),
                        'description_length' => strlen($description),
                    ]);
                }

                $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $providerConfigured, $product);
                continue;
            }

            if (! $providerConfigured) {
                Log::warning('Skipping translation - API not configured', [
                    'product_id' => $product->id,
                    'locale' => $locale,
                ]);
                // Skip translating to other locales when provider is not available
                $this->translateVariants($product->variants ?? [], $locale, $sourceLocale, $force, $providerConfigured, $product);
                continue;
            }

            // ENHANCED: Use batch translation for better performance
            Log::info('Starting batch translation', [
                'product_id' => $product->id,
                'locale' => $locale,
                'source_locale' => $sourceLocale,
                'force' => $force,
            ]);
            
            $this->batchTranslateProduct($product, $locale, $sourceLocale, $force);
        }
    }

    private function isProviderConfigured(): bool
    {
        $provider = (string) config('services.translation_provider', 'libre_translate');

        if ($provider === 'deepseek') {
            return ! empty(config('services.deepseek.key'));
        }

        if ($provider === 'libre_translate') {
            return ! empty(config('services.libre_translate.base_url'));
        }

        return true;
    }

    /**
     * Enhanced batch translation for product and variants
     */
    private function batchTranslateProduct(Product $product, string $locale, string $sourceLocale, bool $force): void
    {
        $textsToTranslate = [];
        $textMetadata = [];

        // Collect product name (only if not already translated)
        $name = trim((string) $product->name);
        $existingNameTranslation = $product->translationForLocale($locale)?->name;
        if ($name !== '' && (!$existingNameTranslation || $force)) {
            $textsToTranslate[] = $name;
            $textMetadata[] = ['type' => 'product_name', 'id' => $product->id];
        } elseif ($existingNameTranslation) {
            Log::info('Skipping product name in batch - already exists', [
                'product_id' => $product->id,
                'locale' => $locale,
                'existing_name' => substr($existingNameTranslation, 0, 50),
            ]);
        }

        // Collect product description (only if not already translated)
        $description = trim((string) ($product->description ?? ''));
        $existingDescTranslation = $product->translationForLocale($locale)?->description;
        if ($description !== '' && (!$existingDescTranslation || $force)) {
            // Truncate long descriptions to avoid context limits
            if (strlen($description) > self::MAX_TEXT_LENGTH) {
                $description = substr($description, 0, self::MAX_TEXT_LENGTH - 3) . '...';
                Log::info('Description truncated for batch translation', [
                    'product_id' => $product->id,
                    'original_length' => strlen($product->description),
                    'truncated_length' => strlen($description),
                ]);
            }
            $textsToTranslate[] = $description;
            $textMetadata[] = ['type' => 'product_description', 'id' => $product->id];
        } elseif ($existingDescTranslation) {
            Log::info('Skipping product description in batch - already exists', [
                'product_id' => $product->id,
                'locale' => $locale,
                'existing_desc_length' => strlen($existingDescTranslation),
            ]);
        }

        // Collect variant titles (only if not already translated)
        foreach ($product->variants ?? [] as $variant) {
            $title = trim((string) ($variant->title ?? ''));
            if ($title === '') {
                continue;
            }

            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translations = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];
            $existingVariantTranslation = $translations[$locale]['title'] ?? null;

            if (!$existingVariantTranslation || $force) {
                $textsToTranslate[] = $title;
                $textMetadata[] = ['type' => 'variant_title', 'id' => $variant->id];
            } else {
                Log::info('Skipping variant in batch - already exists', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'locale' => $locale,
                    'existing_title' => substr($existingVariantTranslation, 0, 50),
                ]);
            }
        }

        if (empty($textsToTranslate)) {
            Log::info('No texts to translate in batch - all already exist', [
                'product_id' => $product->id,
                'locale' => $locale,
                'force' => $force,
            ]);
            return;
        }

        $allTranslations = array_fill(0, count($textsToTranslate), '');

        // De-duplicate repeated texts (very common with variant titles) to cut API usage.
        $uniqueTexts = [];
        $uniqueTextMap = [];
        foreach ($textsToTranslate as $index => $text) {
            $key = md5($text);
            if (! isset($uniqueTexts[$key])) {
                $uniqueTexts[$key] = $text;
            }
            $uniqueTextMap[$key][] = $index;
        }

        // Cache-first resolution before any API call.
        $pendingUniqueTexts = [];
        foreach ($uniqueTexts as $key => $text) {
            $cached = Cache::get($this->translationCacheKey($text, $sourceLocale, $locale));
            if (is_string($cached) && $cached !== '') {
                foreach ($uniqueTextMap[$key] as $index) {
                    $allTranslations[$index] = $cached;
                }
                continue;
            }

            $pendingUniqueTexts[$key] = $text;
        }

        // Only unresolved unique texts go to API.
        $pendingTexts = array_values($pendingUniqueTexts);
        foreach ($this->buildCharAwareBatches($pendingTexts) as $batchIndex => $batch) {
            try {
                $batchTranslations = $this->callBatchTranslationAPI($batch, $sourceLocale, $locale, $product->id);

                foreach ($batch as $i => $sourceText) {
                    $translated = $batchTranslations[$i] ?? $sourceText;
                    $key = md5($sourceText);
                    Cache::put($this->translationCacheKey($sourceText, $sourceLocale, $locale), $translated, self::CACHE_TTL);

                    foreach ($uniqueTextMap[$key] ?? [] as $index) {
                        $allTranslations[$index] = $translated;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Batch translation failed, falling back to individual calls', [
                    'product_id' => $product->id,
                    'batch_index' => $batchIndex,
                    'error' => $e->getMessage(),
                ]);

                foreach ($batch as $sourceText) {
                    $key = md5($sourceText);

                    try {
                        $translated = $this->translateWithCache($sourceText, $sourceLocale, $locale, $product->id);
                    } catch (\Throwable $individualError) {
                        Log::error('Individual translation failed', [
                            'product_id' => $product->id,
                            'text' => substr($sourceText, 0, 50),
                            'error' => $individualError->getMessage(),
                        ]);
                        $translated = $sourceText;
                    }

                    foreach ($uniqueTextMap[$key] ?? [] as $index) {
                        $allTranslations[$index] = $translated;
                    }
                }
            }
        }

        // Ensure no empty slots remain.
        foreach ($allTranslations as $index => $value) {
            if ($value === '') {
                $allTranslations[$index] = $textsToTranslate[$index];
            }
        }

        // Apply all translations
        $this->applyBatchTranslations($allTranslations, $textMetadata, $product, $locale);
    }

    /**
     * Call batch translation API
     * @param array<int, string> $texts
     * @return array<int, string>
     */
    private function callBatchTranslationAPI(array $texts, string $sourceLocale, string $targetLocale, int $productId): array
    {
        // Check if client supports batch translation
        if (! method_exists($this->client, 'chat')) {
            throw new \RuntimeException('Translation client does not support batch translation');
        }

        $targetName = $this->localeToLanguage($targetLocale);
        $sourceName = $this->localeToLanguage($sourceLocale);
        
        // Create improved batch prompt
        $textList = '';
        foreach ($texts as $index => $text) {
            $textList .= ($index + 1) . ". \"{$text}\"\n";
        }

        $prompt = "Translate the following numbered texts from {$sourceName} to {$targetName}. "
            . "Return ONLY a JSON object with the same numbering, where each number maps to the translation. "
            . "Do not include any explanations, notes, or additional text. "
            . "Format exactly: {\"1\": \"translation1\", \"2\": \"translation2\", \"3\": \"translation3\"}\n\n"
            . "Texts to translate:\n{$textList}";

        $response = $this->client->chat([
            [
                'role' => 'system',
                'content' => 'You are a professional translator. Return ONLY valid JSON. No explanations, no markdown, no extra text. Just the JSON object.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ], 0.1, 90); // 90 second timeout for batch

        // Clean up response - remove markdown formatting if present
        $response = trim($response);
        if (str_starts_with($response, '```json')) {
            $response = substr($response, 7);
        }
        if (str_ends_with($response, '```')) {
            $response = substr($response, 0, -3);
        }
        $response = trim($response);

        $decoded = json_decode($response, true);
        if (! is_array($decoded)) {
            Log::warning('Invalid JSON from batch API, response was', [
                'product_id' => $productId,
                'response' => substr($response, 0, 200),
            ]);
            throw new \RuntimeException('Invalid JSON response from batch translation');
        }

        // Map back to original order
        $translations = [];
        foreach ($texts as $index => $text) {
            $key = (string) ($index + 1);
            $translation = $decoded[$key] ?? $text;
            
            // Validate translation
            if ($this->isLikelySourceLanguage($translation, $sourceLocale, $targetLocale)) {
                Log::warning('Batch translation appears to be in source language, using original', [
                    'product_id' => $productId,
                    'text' => substr($text, 0, 50),
                    'translation' => substr($translation, 0, 50),
                ]);
                $translations[] = $text;
            } else {
                $translations[] = trim($translation) !== '' ? trim($translation) : $text;
            }
        }

        return $translations;
    }

    /**
     * Translate with caching for individual texts
     */
    private function translateWithCache(string $text, string $source, string $target, int $productId): string
    {
        $cacheKey = $this->translationCacheKey($text, $source, $target);
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info('Using cached translation', [
                'product_id' => $productId,
                'text' => substr($text, 0, 50),
            ]);
            return $cached;
        }

        $translation = $this->client->translate($text, $source, $target);
        
        // Cache individual translation
        Cache::put($cacheKey, $translation, self::CACHE_TTL);
        
        return $translation;
    }

    private function translationCacheKey(string $text, string $source, string $target): string
    {
        $provider = (string) config('services.translation_provider', 'unknown');

        return "translation:{$provider}:{$source}:{$target}:" . md5($text);
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, string>>
     */
    private function buildCharAwareBatches(array $texts): array
    {
        $batches = [];
        $currentBatch = [];
        $currentChars = 0;

        foreach ($texts as $text) {
            $length = strlen($text);
            $wouldExceedCount = count($currentBatch) >= self::BATCH_SIZE;
            $wouldExceedChars = ($currentChars + $length) > self::BATCH_CHAR_BUDGET;

            if ($currentBatch !== [] && ($wouldExceedCount || $wouldExceedChars)) {
                $batches[] = $currentBatch;
                $currentBatch = [];
                $currentChars = 0;
            }

            $currentBatch[] = $text;
            $currentChars += $length;
        }

        if ($currentBatch !== []) {
            $batches[] = $currentBatch;
        }

        return $batches;
    }

    /**
     * Apply batch translations to product and variants
     */
    private function applyBatchTranslations(array $translations, array $metadata, Product $product, string $locale): void
    {
        $productUpdate = [];
        $variantTranslations = [];

        foreach ($translations as $index => $translation) {
            if (! isset($metadata[$index])) continue;

            $meta = $metadata[$index];
            
            if ($meta['type'] === 'product_name') {
                $productUpdate['name'] = $translation;
            } elseif ($meta['type'] === 'product_description') {
                $productUpdate['description'] = $translation;
            } elseif ($meta['type'] === 'variant_title') {
                $variantTranslations[$meta['id']] = $translation;
            }
        }

        // Update product translation
        if (! empty($productUpdate)) {
            ProductTranslation::updateOrCreate(
                ['product_id' => $product->id, 'locale' => $locale],
                $productUpdate
            );
        }

        // Update variant translations
        $this->updateVariantTranslations($product->variants ?? [], $variantTranslations, $locale);
    }

    /**
     * Update variant translations from batch results
     */
    private function updateVariantTranslations(iterable $variants, array $translations, string $locale): void
    {
        foreach ($variants as $variant) {
            $variantId = (string) ($variant->id ?? '');
            if (! isset($translations[$variantId])) {
                continue;
            }

            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translationsData = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];

            $translationsData[$locale]['title'] = $translations[$variantId];
            $metadata['translations'] = $translationsData;
            $variant->metadata = $metadata;
            $variant->save();
        }
    }

    /**
     * Translate variant titles and store per-locale copies inside metadata.
     *
     * @param iterable<int, mixed> $variants
     */
    private function translateVariants(iterable $variants, string $locale, string $sourceLocale, bool $force, bool $apiKeyConfigured, Product $product): void
    {
        $variantsProcessed = 0;
        $variantsSkipped = 0;

        foreach ($variants as $variant) {
            $title = trim((string) ($variant->title ?? ''));
            if ($title === '') {
                $variantsSkipped++;
                continue;
            }

            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $translations = is_array($metadata['translations'] ?? null) ? $metadata['translations'] : [];
            $existing = $translations[$locale]['title'] ?? null;

            // ENHANCED: Better variant skipping logic
            if (! $force && $existing) {
                Log::info('Skipping variant translation - already exists', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id ?? null,
                    'locale' => $locale,
                    'existing_title' => substr($existing, 0, 50),
                    'force' => $force,
                ]);
                $variantsSkipped++;
                continue;
            }

            if ($locale === $sourceLocale) {
                $translations[$locale]['title'] = $title;
                $metadata['translations'] = $translations;
                $variant->metadata = $metadata;
                $variant->save();
                
                Log::info('Source variant translation saved', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id ?? null,
                    'locale' => $locale,
                    'title' => substr($title, 0, 50),
                ]);
                
                $variantsProcessed++;
                continue;
            }

            if (! $apiKeyConfigured) {
                Log::warning('Skipping variant translation - API not configured', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id ?? null,
                    'locale' => $locale,
                ]);
                $variantsSkipped++;
                continue;
            }

            // Use cached translation for variants
            try {
                $candidate = $this->translateWithCache($title, $sourceLocale, $locale, $product->id);

                if ($this->isLikelySourceLanguage($candidate, $sourceLocale, $locale)) {
                    Log::warning('Variant translation appears to be in source language, skipping', [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id ?? null,
                        'locale' => $locale,
                        'original' => substr($title, 0, 50),
                        'translation' => substr($candidate, 0, 50),
                    ]);
                    $variantsSkipped++;
                    continue;
                }

                $translations[$locale]['title'] = $candidate !== '' ? $candidate : $title;
                $metadata['translations'] = $translations;
                $variant->metadata = $metadata;
                $variant->save();
                
                Log::info('Variant translation completed', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id ?? null,
                    'locale' => $locale,
                    'original' => substr($title, 0, 50),
                    'translation' => substr($candidate, 0, 50),
                ]);
                
                $variantsProcessed++;

            } catch (\Throwable $e) {
                Log::error('Translation failed for product variant title', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id ?? null,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
                $variantsSkipped++;
            }
        }

        // ENHANCED: Log variant processing summary
        if ($variantsProcessed > 0 || $variantsSkipped > 0) {
            Log::info('Variant translation summary', [
                'product_id' => $product->id,
                'locale' => $locale,
                'variants_processed' => $variantsProcessed,
                'variants_skipped' => $variantsSkipped,
                'total_variants' => $variantsProcessed + $variantsSkipped,
            ]);
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

    private function localeToLanguage(string $locale): string
    {
        $map = [
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'sv' => 'Swedish',
            'fi' => 'Finnish',
            'no' => 'Norwegian',
            'da' => 'Danish',
        ];

        return $map[$locale] ?? $locale;
    }
}
