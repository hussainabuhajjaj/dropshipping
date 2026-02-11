<?php

declare(strict_types=1);

namespace App\Services\AI;

class HomePageSettingTranslationService
{
    public function __construct(private ContentTranslationService $translator)
    {
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function translateState(array $state, string $sourceLocale = 'en', string $targetLocale = 'fr'): array
    {
        $state['top_strip'] = $this->translateTopStrip($state['top_strip'] ?? null, $sourceLocale, $targetLocale);
        $state['hero_slides'] = $this->translateHeroSlides($state['hero_slides'] ?? null, $sourceLocale, $targetLocale);
        $state['rail_cards'] = $this->translateRailCards($state['rail_cards'] ?? null, $sourceLocale, $targetLocale);
        $state['banner_strip'] = $this->translateBannerStrip($state['banner_strip'] ?? null, $sourceLocale, $targetLocale);

        return $state;
    }

    /**
     * @return array<int, mixed>
     */
    private function translateTopStrip(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $items = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
            $subtitle = is_string($item['subtitle'] ?? null) ? trim($item['subtitle']) : '';

            if ($title !== '') {
                $toTranslate["top_strip_{$i}_title"] = $title;
            }
            if ($subtitle !== '') {
                $toTranslate["top_strip_{$i}_subtitle"] = $subtitle;
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $titleKey = "top_strip_{$i}_title";
            $subtitleKey = "top_strip_{$i}_subtitle";

            if (isset($translated[$titleKey]) && is_string($translated[$titleKey]) && trim($translated[$titleKey]) !== '') {
                $item['title'] = $translated[$titleKey];
            }
            if (isset($translated[$subtitleKey]) && is_string($translated[$subtitleKey]) && trim($translated[$subtitleKey]) !== '') {
                $item['subtitle'] = $translated[$subtitleKey];
            }

            $items[$i] = $item;
        }

        return array_values($items);
    }

    /**
     * @return array<int, mixed>
     */
    private function translateHeroSlides(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $items = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $fields = [
                'kicker' => is_string($item['kicker'] ?? null) ? trim($item['kicker']) : '',
                'title' => is_string($item['title'] ?? null) ? trim($item['title']) : '',
                'subtitle' => is_string($item['subtitle'] ?? null) ? trim($item['subtitle']) : '',
                'primary_label' => is_string($item['primary_label'] ?? null) ? trim($item['primary_label']) : '',
                'secondary_label' => is_string($item['secondary_label'] ?? null) ? trim($item['secondary_label']) : '',
            ];

            foreach ($fields as $field => $text) {
                if ($text === '') {
                    continue;
                }
                $toTranslate["hero_{$i}_{$field}"] = $text;
            }

            $meta = $item['meta'] ?? null;
            if (is_array($meta)) {
                foreach ($meta as $j => $tag) {
                    if (! is_string($tag)) {
                        continue;
                    }
                    $tag = trim($tag);
                    if ($tag === '') {
                        continue;
                    }
                    $toTranslate["hero_{$i}_meta_{$j}"] = $tag;
                }
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $keys = [
                'kicker',
                'title',
                'subtitle',
                'primary_label',
                'secondary_label',
            ];

            foreach ($keys as $key) {
                $tKey = "hero_{$i}_{$key}";
                if (isset($translated[$tKey]) && is_string($translated[$tKey]) && trim($translated[$tKey]) !== '') {
                    $item[$key] = $translated[$tKey];
                }
            }

            $meta = $item['meta'] ?? null;
            if (is_array($meta)) {
                foreach ($meta as $j => $tag) {
                    $tKey = "hero_{$i}_meta_{$j}";
                    if (isset($translated[$tKey]) && is_string($translated[$tKey]) && trim($translated[$tKey]) !== '') {
                        $meta[$j] = $translated[$tKey];
                    }
                }
                $item['meta'] = array_values($meta);
            }

            $items[$i] = $item;
        }

        return array_values($items);
    }

    /**
     * @return array<int, mixed>
     */
    private function translateRailCards(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $items = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $fields = [
                'kicker' => is_string($item['kicker'] ?? null) ? trim($item['kicker']) : '',
                'title' => is_string($item['title'] ?? null) ? trim($item['title']) : '',
                'subtitle' => is_string($item['subtitle'] ?? null) ? trim($item['subtitle']) : '',
                'cta' => is_string($item['cta'] ?? null) ? trim($item['cta']) : '',
            ];

            foreach ($fields as $field => $text) {
                if ($text === '') {
                    continue;
                }
                $toTranslate["rail_{$i}_{$field}"] = $text;
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (['kicker', 'title', 'subtitle', 'cta'] as $key) {
                $tKey = "rail_{$i}_{$key}";
                if (isset($translated[$tKey]) && is_string($translated[$tKey]) && trim($translated[$tKey]) !== '') {
                    $item[$key] = $translated[$tKey];
                }
            }

            $items[$i] = $item;
        }

        return array_values($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function translateBannerStrip(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $strip = is_array($value) ? $value : [];

        $toTranslate = [];
        $kicker = is_string($strip['kicker'] ?? null) ? trim($strip['kicker']) : '';
        $title = is_string($strip['title'] ?? null) ? trim($strip['title']) : '';
        $cta = is_string($strip['cta'] ?? null) ? trim($strip['cta']) : '';

        if ($kicker !== '') {
            $toTranslate['banner_kicker'] = $kicker;
        }
        if ($title !== '') {
            $toTranslate['banner_title'] = $title;
        }
        if ($cta !== '') {
            $toTranslate['banner_cta'] = $cta;
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        if (isset($translated['banner_kicker']) && is_string($translated['banner_kicker']) && trim($translated['banner_kicker']) !== '') {
            $strip['kicker'] = $translated['banner_kicker'];
        }
        if (isset($translated['banner_title']) && is_string($translated['banner_title']) && trim($translated['banner_title']) !== '') {
            $strip['title'] = $translated['banner_title'];
        }
        if (isset($translated['banner_cta']) && is_string($translated['banner_cta']) && trim($translated['banner_cta']) !== '') {
            $strip['cta'] = $translated['banner_cta'];
        }

        return $strip;
    }
}

