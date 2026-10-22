<?php

declare(strict_types=1);

namespace App\Support;

final class StorefrontSpecs
{
    /**
     * Convert product attributes into customer-facing specs.
     *
     * Supplier payloads can contain IDs, raw JSON, images, variants, inventory,
     * and marketplace names. None of that should be exposed on the storefront.
     *
     * @param  array<string, mixed>|null  $attributes
     * @return array<string, string>
     */
    public static function fromAttributes(?array $attributes): array
    {
        if (! is_array($attributes) || $attributes === []) {
            return [];
        }

        $payload = self::productPayload($attributes);
        $specs = [];

        self::put($specs, 'Item type', self::firstValue($payload, ['entryNameEn', 'type', 'item_type']));
        self::put($specs, 'Material', self::firstValue($payload, ['materialNameEnSet', 'materialNameEn', 'material']));
        self::put($specs, 'Packing', self::firstValue($payload, ['packingNameEnSet', 'packingNameEn', 'packing']));
        self::put($specs, 'Product weight', self::formatGramRange(self::firstRawValue($payload, ['productWeight', 'weight_grams'])));
        self::put($specs, 'Packed weight', self::formatGramRange(self::firstRawValue($payload, ['packingWeight'])));

        $directAttributes = self::looksLikeSupplierPayload($attributes) ? [] : $attributes;

        foreach ($directAttributes as $key => $value) {
            if (! self::isSafeDirectSpecKey((string) $key)) {
                continue;
            }

            self::put($specs, self::humanizeKey((string) $key), self::normalizeValue($value));
        }

        return $specs;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function productPayload(array $attributes): array
    {
        $payload = $attributes['cj_payload']
            ?? $attributes['product_payload']
            ?? $attributes['raw_payload']
            ?? $attributes;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private static function firstValue(array $source, array $keys): ?string
    {
        return self::normalizeValue(self::firstRawValue($source, $keys));
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private static function firstRawValue(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $specs
     */
    private static function put(array &$specs, string $label, ?string $value): void
    {
        $label = trim($label);
        $value = $value !== null ? trim($value) : '';

        if ($label === '' || $value === '' || self::containsBlockedText($label) || self::containsBlockedText($value)) {
            return;
        }

        $specs[$label] = $value;
    }

    private static function normalizeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            $decoded = self::decodeJsonString($value);
            if ($decoded !== null) {
                return self::normalizeValue($decoded);
            }

            if (self::looksLikeRawData($value) || self::containsBlockedText($value)) {
                return null;
            }

            return self::cleanText($value);
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                return null;
            }

            $items = array_values(array_filter(array_map(
                fn (mixed $item) => is_scalar($item) || $item === null ? self::normalizeValue($item) : null,
                $value,
            )));

            return $items === [] ? null : implode(', ', array_unique($items));
        }

        return null;
    }

    private static function decodeJsonString(string $value): mixed
    {
        if (! str_starts_with($value, '[') && ! str_starts_with($value, '{')) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private static function formatGramRange(mixed $value): ?string
    {
        $value = self::normalizeValue($value);
        if ($value === null) {
            return null;
        }

        $parts = preg_split('/\s*-\s*/', $value) ?: [];
        $numbers = array_values(array_filter(array_map(
            fn (string $part) => is_numeric($part) ? self::trimNumber((float) $part) : null,
            $parts,
        )));

        if ($numbers === []) {
            return null;
        }

        return implode('-', $numbers) . ' g';
    }

    private static function trimNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private static function isSafeDirectSpecKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (preg_match('/(cj|payload|variant|inventory|image|video|sku|pid|supplier|source|status|listed|price|cost|token|secret|url|html|description|categoryid|entrycode|productname|productunit|producttype|productpro|entryname|categoryname|materialname|packingname|productkey|packingkey|materialkey)/', $normalized)) {
            return false;
        }

        return preg_match('/(material|color|colour|size|capacity|weight|dimension|length|width|height|shape|pattern|style|type|packing|packaging)/', $normalized) === 1;
    }

    private static function humanizeKey(string $key): string
    {
        return ucfirst(trim((string) preg_replace('/[_-]+/', ' ', $key)));
    }

    private static function looksLikeRawData(string $value): bool
    {
        return str_contains($value, '://')
            || str_contains($value, '<')
            || str_contains($value, '>')
            || strlen($value) > 180;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function looksLikeSupplierPayload(array $attributes): bool
    {
        foreach (['pid', 'productName', 'productSku', 'variants', 'productImage', 'sellPrice'] as $key) {
            if (array_key_exists($key, $attributes)) {
                return true;
            }
        }

        return false;
    }

    private static function containsBlockedText(string $value): bool
    {
        return preg_match('/\b(cj|cjdropshipping|payload|sku|pid|supplier)\b/i', $value) === 1;
    }

    private static function cleanText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B,");
    }
}
