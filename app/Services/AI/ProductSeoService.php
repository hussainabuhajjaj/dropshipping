<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Products\Models\Product;
use App\Models\ProductTranslation;

class ProductSeoService
{
    public function __construct(private DeepSeekClient $client, private ProductTranslationService $translationService, private ModerationService $moderationService)
    {
    }

    public function generate(Product $product, string $locale = 'en', bool $force = false): void
    {
        if (! $force && $product->meta_title && $product->meta_description) {
            return;
        }

        // Prefer translated content when requesting non-default locales
        $name = trim((string) $product->name);
        $description = trim((string) ($product->description ?? ''));

        if ($locale !== 'en') {
            $translation = $product->translationForLocale($locale);

            if (! $translation) {
                // Create translation on the fly so the SEO generator has proper locale text.
                // Force true to ensure we get a translation now.
                $this->translationService->translate($product, [$locale], 'en', true);
                $product = $product->fresh();
                $translation = $product->translationForLocale($locale);
            }

            if ($translation instanceof ProductTranslation) {
                $name = trim((string) ($translation->name ?? $name));
                $description = trim((string) ($translation->description ?? $description));
            }
        }

        if ($name === '' && $description === '') {
            return;
        }

        $prompt = "Generate SEO metadata for an ecommerce product in {$locale}.\n"
            . "Return JSON with keys meta_title and meta_description.\n"
            . "Constraints: meta_title <= 60 chars, meta_description <= 160 chars.\n"
            . "Use concise, human-friendly phrasing without quotes.\n\n"
            . "Product name: {$name}\n"
            . "Description: {$description}";

        $content = $this->client->chat([
            [
                'role' => 'system',
                'content' => 'You generate short SEO metadata for ecommerce products. Return ONLY a JSON object with keys meta_title and meta_description.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ], 0.3);

        $decoded = $this->parseJsonResponse($content);
        if (! is_array($decoded) || empty($decoded)) {
            return;
        }

        $metaTitle = trim((string) ($decoded['meta_title'] ?? ''));
        $metaDescription = trim((string) ($decoded['meta_description'] ?? ''));

        // Moderation: use ModerationService
        if (! $this->moderationService->isAllowed([$metaTitle, $metaDescription])) {
            logger()->warning('SEO generation flagged by moderation', ['product_id' => $product->id, 'locale' => $locale]);
            return;
        }

        if (! $force) {
            $metaTitle = $product->meta_title ?: $metaTitle;
            $metaDescription = $product->meta_description ?: $metaDescription;
        }

        if ($metaTitle === '' && $metaDescription === '') {
            return;
        }

        // Persist SEO fields for the primary locale fields
        $update = [
            'meta_title' => $metaTitle ?: $product->meta_title,
            'meta_description' => $metaDescription ?: $product->meta_description,
        ];

        // Record per-locale SEO metadata and provenance
        $seoMetadata = is_array($product->seo_metadata) ? $product->seo_metadata : [];
        $seoMetadata[$locale] = array_merge($seoMetadata[$locale] ?? [], [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'generated_at' => now()->toDateTimeString(),
            'provider' => 'deepseek',
        ]);

        $update['seo_metadata'] = $seoMetadata;

        $product->update($update);
    }

    /**
     * Attempt to extract a JSON object from possibly noisy model output.
     * Returns decoded array or null when parsing fails.
     *
     * @return array<string, mixed>|null
     */
    private function parseJsonResponse(string $content): ?array
    {
        // First try strict decoding
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try extracting the first JSON object substring
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($sub, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback to simple key: value parsing
        $lines = preg_split('/\r?\n/', $content);
        $result = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*meta_title\s*[:=]\s*(.+)$/i', $line, $m)) {
                $result['meta_title'] = trim($m[1], " \t\"'\\");
            }
            if (preg_match('/^\s*meta_description\s*[:=]\s*(.+)$/i', $line, $m)) {
                $result['meta_description'] = trim($m[1], " \t\"'\\");
            }
        }

        return $result === [] ? null : $result;
    }

}


