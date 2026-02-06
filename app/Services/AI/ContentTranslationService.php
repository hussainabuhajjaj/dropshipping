<?php

declare(strict_types=1);

namespace App\Services\AI;

class ContentTranslationService
{
    public function __construct(private TranslationProvider $translator)
    {
    }

    public function translateText(?string $text, string $sourceLocale = 'en', string $targetLocale = 'fr'): ?string
    {
        $text = is_string($text) ? trim($text) : '';

        if ($text === '') {
            return null;
        }

        return trim((string) $this->translator->translate($text, $sourceLocale, $targetLocale));
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function translateFields(array $fields, string $sourceLocale = 'en', string $targetLocale = 'fr'): array
    {
        $result = [];
        $toTranslate = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_string($value)) {
                $result[$key] = $value;
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                $result[$key] = $value;
                continue;
            }

            $toTranslate[$key] = $value;
        }

        if ($toTranslate === []) {
            return $result;
        }

        if ($this->translator instanceof DeepSeekClient) {
            try {
                $translated = $this->deepSeekTranslateObject($toTranslate, $sourceLocale, $targetLocale);
                foreach ($toTranslate as $key => $value) {
                    $result[$key] = isset($translated[$key]) && is_string($translated[$key]) && trim($translated[$key]) !== ''
                        ? trim($translated[$key])
                        : $value;
                }

                return $result;
            } catch (\Throwable) {
                // fall back to per-field translation
            }
        }

        foreach ($toTranslate as $key => $value) {
            $result[$key] = trim((string) $this->translator->translate($value, $sourceLocale, $targetLocale));
        }

        return $result;
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function deepSeekTranslateObject(array $data, string $sourceLocale, string $targetLocale): array
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $targetName = $this->translator->localeToLanguage($targetLocale);
        $sourceName = $this->translator->localeToLanguage($sourceLocale);

        $content = $this->translator->chat([
            [
                'role' => 'system',
                'content' => "You are a translator.\n"
                    . "Translate from {$sourceName} to {$targetName}.\n"
                    . "Input will be a JSON object. Return ONLY valid JSON with the SAME keys and translated string values.\n"
                    . "Rules:\n"
                    . "- Keep URLs, route paths (e.g. /(tabs)/home), and brand names unchanged.\n"
                    . "- Preserve HTML tags/attributes if present; translate only human-readable text.\n"
                    . "- Do not add extra keys. Do not wrap in markdown.\n",
            ],
            [
                'role' => 'user',
                'content' => "Translate this JSON object values to {$targetName}:\n{$payload}",
            ],
        ], 0.1);

        $decoded = $this->parseJsonObject($content);

        if (! is_array($decoded)) {
            return [];
        }

        $translated = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }
            $translated[$key] = $value;
        }

        return $translated;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonObject(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $sub = substr($content, $start, $end - $start + 1);
        $decoded = json_decode($sub, true);

        return is_array($decoded) ? $decoded : null;
    }
}
