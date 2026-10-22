<?php

declare(strict_types=1);

namespace App\Services\AI;

class StorefrontSettingTranslationService
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
        $state = $this->translateScalars($state, $sourceLocale, $targetLocale);
        $state['header_links'] = $this->translateHeaderLinks($state['header_links'] ?? null, $sourceLocale, $targetLocale);
        $state['footer_columns'] = $this->translateFooterColumns($state['footer_columns'] ?? null, $sourceLocale, $targetLocale);
        $state['value_props'] = $this->translateValueProps($state['value_props'] ?? null, $sourceLocale, $targetLocale);
        $state['social_links'] = $this->translateSocialLinks($state['social_links'] ?? null, $sourceLocale, $targetLocale);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function translateScalars(array $state, string $sourceLocale, string $targetLocale): array
    {
        $translated = $this->translator->translateFields([
            'footer_blurb' => is_string($state['footer_blurb'] ?? null) ? $state['footer_blurb'] : '',
            'delivery_notice' => is_string($state['delivery_notice'] ?? null) ? $state['delivery_notice'] : '',
            'coming_soon_title' => is_string($state['coming_soon_title'] ?? null) ? $state['coming_soon_title'] : '',
            'coming_soon_message' => is_string($state['coming_soon_message'] ?? null) ? $state['coming_soon_message'] : '',
            'coming_soon_cta_label' => is_string($state['coming_soon_cta_label'] ?? null) ? $state['coming_soon_cta_label'] : '',
            'newsletter_popup_title' => is_string($state['newsletter_popup_title'] ?? null) ? $state['newsletter_popup_title'] : '',
            'newsletter_popup_body' => is_string($state['newsletter_popup_body'] ?? null) ? $state['newsletter_popup_body'] : '',
            'newsletter_popup_incentive' => is_string($state['newsletter_popup_incentive'] ?? null) ? $state['newsletter_popup_incentive'] : '',
        ], $sourceLocale, $targetLocale);

        foreach ($translated as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            if (trim($value) === '') {
                continue;
            }

            $state[$key] = $value;
        }

        return $state;
    }

    /**
     * @return array<int, mixed>
     */
    private function translateHeaderLinks(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $links = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($links as $i => $link) {
            if (! is_array($link)) {
                continue;
            }

            $label = is_string($link['label'] ?? null) ? trim($link['label']) : '';
            if ($label !== '') {
                $toTranslate["header_{$i}_label"] = $label;
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($links as $i => $link) {
            if (! is_array($link)) {
                continue;
            }

            $tKey = "header_{$i}_label";
            if (isset($translated[$tKey]) && is_string($translated[$tKey]) && trim($translated[$tKey]) !== '') {
                $link['label'] = $translated[$tKey];
            }

            $links[$i] = $link;
        }

        return array_values($links);
    }

    /**
     * @return array<int, mixed>
     */
    private function translateFooterColumns(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $columns = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($columns as $i => $column) {
            if (! is_array($column)) {
                continue;
            }

            $title = is_string($column['title'] ?? null) ? trim($column['title']) : '';
            if ($title !== '') {
                $toTranslate["footer_{$i}_title"] = $title;
            }

            $links = $column['links'] ?? null;
            if (is_array($links)) {
                foreach ($links as $j => $link) {
                    if (! is_array($link)) {
                        continue;
                    }

                    $label = is_string($link['label'] ?? null) ? trim($link['label']) : '';
                    if ($label !== '') {
                        $toTranslate["footer_{$i}_link_{$j}_label"] = $label;
                    }
                }
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($columns as $i => $column) {
            if (! is_array($column)) {
                continue;
            }

            $tTitle = "footer_{$i}_title";
            if (isset($translated[$tTitle]) && is_string($translated[$tTitle]) && trim($translated[$tTitle]) !== '') {
                $column['title'] = $translated[$tTitle];
            }

            $links = $column['links'] ?? null;
            if (is_array($links)) {
                foreach ($links as $j => $link) {
                    if (! is_array($link)) {
                        continue;
                    }

                    $tLabel = "footer_{$i}_link_{$j}_label";
                    if (isset($translated[$tLabel]) && is_string($translated[$tLabel]) && trim($translated[$tLabel]) !== '') {
                        $link['label'] = $translated[$tLabel];
                    }

                    $links[$j] = $link;
                }

                $column['links'] = array_values($links);
            }

            $columns[$i] = $column;
        }

        return array_values($columns);
    }

    /**
     * @return array<int, mixed>
     */
    private function translateValueProps(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $items = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
            $body = is_string($item['body'] ?? null) ? trim($item['body']) : '';

            if ($title !== '') {
                $toTranslate["value_prop_{$i}_title"] = $title;
            }
            if ($body !== '') {
                $toTranslate["value_prop_{$i}_body"] = $body;
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $tTitle = "value_prop_{$i}_title";
            $tBody = "value_prop_{$i}_body";

            if (isset($translated[$tTitle]) && is_string($translated[$tTitle]) && trim($translated[$tTitle]) !== '') {
                $item['title'] = $translated[$tTitle];
            }
            if (isset($translated[$tBody]) && is_string($translated[$tBody]) && trim($translated[$tBody]) !== '') {
                $item['body'] = $translated[$tBody];
            }

            $items[$i] = $item;
        }

        return array_values($items);
    }

    /**
     * @return array<int, mixed>
     */
    private function translateSocialLinks(mixed $value, string $sourceLocale, string $targetLocale): array
    {
        $items = is_array($value) ? $value : [];

        $toTranslate = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = is_string($item['label'] ?? null) ? trim($item['label']) : '';
            if ($label !== '') {
                $toTranslate["social_{$i}_label"] = $label;
            }
        }

        $translated = $this->translator->translateFields($toTranslate, $sourceLocale, $targetLocale);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $tKey = "social_{$i}_label";
            if (isset($translated[$tKey]) && is_string($translated[$tKey]) && trim($translated[$tKey]) !== '') {
                $item['label'] = $translated[$tKey];
            }

            $items[$i] = $item;
        }

        return array_values($items);
    }
}

