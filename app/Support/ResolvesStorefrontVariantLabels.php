<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

trait ResolvesStorefrontVariantLabels
{
    protected function resolveVariantDisplayTitle(mixed $variant, ?string $fullTitle = null, ?string $productName = null): string
    {
        $optionValues = $this->extractVariantOptionValues($variant);

        if ($optionValues !== []) {
            return implode(' / ', array_unique($optionValues));
        }

        $cjVariantData = is_array($variant->cj_variant_data ?? null) ? $variant->cj_variant_data : [];
        $variantProperty = $cjVariantData['variantProperty'] ?? null;

        if (is_string($variantProperty)) {
            $decoded = json_decode($variantProperty, true);
            $variantProperty = is_array($decoded) ? $decoded : null;
        }

        if (is_array($variantProperty)) {
            $propertyValues = [];
            foreach ($variantProperty as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $value = $entry['propertyValue'] ?? $entry['value'] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $propertyValues[] = trim((string) $value);
                }
            }

            if ($propertyValues !== []) {
                return implode(' / ', array_unique($propertyValues));
            }
        }

        $variantKey = trim((string) ($cjVariantData['variantKey'] ?? ''));
        if ($variantKey !== '') {
            return $variantKey;
        }

        $title = trim((string) ($fullTitle ?? ''));
        $baseName = trim((string) ($productName ?? ''));

        if ($title !== '' && $baseName !== '' && Str::startsWith(mb_strtolower($title), mb_strtolower($baseName))) {
            $title = trim(substr($title, strlen($baseName)));
            $title = ltrim($title, "-:|/, \t");
        }

        return $title !== '' ? $title : 'Variant';
    }

    protected function extractVariantOptionValues(mixed $variant): array
    {
        $options = is_array($variant->options ?? null) ? $variant->options : [];

        return array_values(array_filter(array_map(
            fn ($value) => is_scalar($value) ? trim((string) $value) : '',
            $options
        )));
    }
}
