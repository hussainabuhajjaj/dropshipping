<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Products\Models\Product;

class ProductMarketingService
{
    public function __construct(private DeepSeekClient $client, private ModerationService $moderation)
    {
    }

    public function generate(Product $product, string $locale = 'en', bool $force = false, string $tone = 'friendly'): void
    {
        // Don't overwrite unless forced
        if (! $force) {
            $existing = is_array($product->marketing_metadata) ? ($product->marketing_metadata[$locale] ?? []) : [];
            if (! empty($existing['title']) || ! empty($existing['description'])) {
                return;
            }
        }

        $name = trim((string) $product->name);
        $description = trim((string) ($product->description ?? ''));

        // Prefer translated content when requesting non-default locales
        if ($locale !== 'en') {
            $translation = $product->translationForLocale($locale);
            if ($translation) {
                $name = trim((string) ($translation->name ?? $name));
                $description = trim((string) ($translation->description ?? $description));
            }
        }

        if ($name === '' && $description === '') {
            return;
        }

        $prompt = "Generate a short marketing title (<= 70 chars) and a 2-3 sentence description for an ecommerce product in {$locale}. Use a {$tone} tone. Return JSON with keys title and description.\n\nProduct name: {$name}\nDescription: {$description}";

        $content = $this->client->chat([
            ['role' => 'system', 'content' => 'You are a helpful marketing copywriter for ecommerce listings. Return ONLY a JSON object with keys title and description.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.6);

        $decoded = $this->parseJsonResponse($content);
        if (! is_array($decoded) || empty($decoded)) {
            return;
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $desc = trim((string) ($decoded['description'] ?? ''));

        if (! $this->moderation->isAllowed([$title, $desc])) {
            logger()->warning('Marketing generation flagged by moderation', ['product_id' => $product->id, 'locale' => $locale]);
            return;
        }

        $marketing = is_array($product->marketing_metadata) ? $product->marketing_metadata : [];
        $marketing[$locale] = array_merge($marketing[$locale] ?? [], [
            'title' => $title,
            'description' => $desc,
            'generated_at' => now()->toDateTimeString(),
            'provider' => 'deepseek',
            'tone' => $tone,
        ]);

        $product->update(['marketing_metadata' => $marketing]);
    }

    /**
     * Attempt to extract a JSON object from possibly noisy model output.
     * Returns decoded array or null when parsing fails.
     *
     * @return array<string, mixed>|null
     */
    private function parseJsonResponse(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($sub, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $lines = preg_split('/\r?\n/', $content);
        $result = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*title\s*[:=]\s*(.+)$/i', $line, $m)) {
                $result['title'] = trim($m[1], " \t\"'\\");
            }
            if (preg_match('/^\s*description\s*[:=]\s*(.+)$/i', $line, $m)) {
                $result['description'] = trim($m[1], " \t\"'\\");
            }
        }

        return $result === [] ? null : $result;
    }
}
