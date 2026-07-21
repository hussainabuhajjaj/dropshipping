<?php

namespace App\Services\Storefront;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductMetaExtractor
{
    private const HIDDEN_ATTRIBUTE_KEYS = [
        'brand',
        'cj_pid',
        'cj_last_payload',
        'cj_last_changed_fields',
        'cj_payload',
        'cjpid',
        'cj',
        'ali_item_id',
        'ali_category_id',
        'ali_product_id',
        'aliexpress_item_id',
        'aliexpress_category_id',
        'supplier_type',
        'supplier_product_id',
        'source_sku',
        'product_subject',
        'product_detail',
        'product_mobile_detail',
        'ae_multimedia',
        'ae_multimedia_info_dto',
        'ae_item_base_info_dto',
        'ae_item_properties',
        'ae_store_info',
        'ae_multimedia_info',
        'detail',
        'mobile_detail',
        'subject',
        'provider',
        'product_provider',
        'supplier',
    ];

    public function extract(array $products): array
    {
        $state = $this->emptyState();

        foreach ($products as $product) {
            $this->ingestProduct($state, $product);
            $this->ingestVariantProperties($state, $product);
        }

        return $this->finalize($state);
    }

    public function extractFromQuery(Builder $query, int $chunkSize = 250, int $maxProducts = 1500): array
    {
        $state = $this->emptyState();

        $ids = (clone $query)
            ->select(['id'])
            ->orderBy('id')
            ->limit($maxProducts)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return $this->finalize($state);
        }

        $idChunks = array_chunk($ids, $chunkSize);
        foreach ($idChunks as $index => $chunk) {
            if (memory_get_usage(true) > 192 * 1024 * 1024) {
                break;
            }

            $products = Product::query()
                ->select(['id', 'attributes'])
                ->with(['variants' => fn ($q) => $q->select(['id', 'product_id', 'options'])])
                ->whereIn('id', $chunk)
                ->get();

            foreach ($products as $product) {
                $this->ingestProduct($state, $product);
                $this->ingestVariantProperties($state, $product);
            }

            unset($products);
            if ($index % 2 === 0) {
                gc_collect_cycles();
            }
        }

        return $this->finalize($state);
    }

    private function shouldSkipAttributeKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        if (in_array($normalized, self::HIDDEN_ATTRIBUTE_KEYS, true)) {
            return true;
        }

        return str_contains($normalized, 'ali_')
            || str_contains($normalized, 'aliexpress')
            || str_contains($normalized, 'ae_')
            || str_contains($normalized, 'supplier_')
            || str_contains($normalized, 'cj_');
    }

    private function emptyState(): array
    {
        return [
            'attributeKeys' => [],
            'attributeOptions' => [],
            'brands' => [],
            'variantAttributeKeys' => [],
        ];
    }

    private function ingestProduct(array &$state, mixed $product): void
    {
        try {
            $attrs = $product->attributes;
            if (!is_array($attrs)) {
                $attrs = json_decode($attrs, true);
                if (!is_array($attrs)) {
                    $attrs = [];
                }
            }

            if (!is_array($attrs)) {
                return;
            }

            foreach ($attrs as $key => $value) {
                if (!is_string($key) || $this->shouldSkipAttributeKey($key)) {
                    continue;
                }

                $state['attributeKeys'][$key] = true;
                if (!isset($state['attributeOptions'][$key])) {
                    $state['attributeOptions'][$key] = [];
                }

                if (is_string($value)) {
                    $state['attributeOptions'][$key][$value] = true;
                } elseif (is_array($value)) {
                    $allStrings = true;
                    foreach ($value as $v) {
                        if (!is_string($v)) {
                            $allStrings = false;
                            break;
                        }
                    }

                    if ($allStrings) {
                        foreach ($value as $v) {
                            $state['attributeOptions'][$key][$v] = true;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function ingestVariantProperties(array &$state, mixed $product): void
    {
        try {
            $variants = $product->variants;
            if (! $variants || $variants->isEmpty()) {
                return;
            }

            foreach ($variants as $variant) {
                $options = $variant->options;
                if (! is_array($options)) {
                    continue;
                }

                $properties = $options['properties'] ?? null;
                $sourceKeys = is_array($properties) ? $properties : $options;

                foreach ($sourceKeys as $key => $value) {
                    if (! is_string($key) || $this->shouldSkipVariantKey($key)) {
                        continue;
                    }

                    $state['attributeKeys'][$key] = true;
                    $state['variantAttributeKeys'][$key] = true;
                    if (! isset($state['attributeOptions'][$key])) {
                        $state['attributeOptions'][$key] = [];
                    }

                    if (is_string($value) && $value !== '') {
                        $state['attributeOptions'][$key][$value] = true;
                    }
                }
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function shouldSkipVariantKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        $skip = [
            'sku_code', 'sku_attr', 'sku_label', 'option', 'properties',
            'cj_vid', 'supplier_sku', 'source_sku',
        ];

        if (in_array($normalized, $skip, true)) {
            return true;
        }

        return $this->shouldSkipAttributeKey($key);
    }

    private function finalize(array $state): array
    {
        $seen = [];
        $seenLabels = [];
        $attributeDefs = [];

        // Variant attributes first — they hold the actual filterable values
        foreach (array_keys($state['variantAttributeKeys']) as $key) {
            $seen[$key] = true;
            $options = array_values(array_filter(array_keys($state['attributeOptions'][$key] ?? []), fn ($v) => $v !== ''));
            $label = $this->inferLabel($key, $options);
            $seenLabels[mb_strtolower($label)] = true;
            $attributeDefs[] = [
                'key' => $key,
                'label' => $label,
                'options' => $options,
            ];
        }

        // Product-level attributes — skip if same key or same label as a variant
        foreach (array_keys($state['attributeKeys']) as $key) {
            if (isset($seen[$key])) {
                continue;
            }
            $options = array_values(array_filter(array_keys($state['attributeOptions'][$key] ?? []), fn ($v) => $v !== ''));
            $label = $this->inferLabel($key, $options);
            if (isset($seenLabels[mb_strtolower($label)])) {
                continue;
            }
            $seenLabels[mb_strtolower($label)] = true;
            $attributeDefs[] = [
                'key' => $key,
                'label' => $label,
                'options' => $options,
            ];
        }

        $brands = array_values(array_filter(array_keys($state['brands']), fn ($v) => $v !== ''));
        if (empty($brands)) {
            $brands = null;
        }

        return [
            'attributeDefs' => $attributeDefs,
            'brands' => $brands,
            'variantAttributeKeys' => array_keys($state['variantAttributeKeys']),
        ];
    }

    /**
     * Infer a human-readable label from a generic key + its option values.
     * "Option1" with values [Red, Blue, Black] → "Color"
     * "Option2" with values [S, M, L, XL] → "Size"
     */
    private function inferLabel(string $key, array $options): string
    {
        if (! $this->isGenericKey($key)) {
            return ucwords(str_replace('_', ' ', $key));
        }

        if ([] !== array_intersect_key(self::COLOR_NAMES, array_flip(array_map('strtolower', $options)))) {
            return 'Color';
        }

        if ($this->valuesLookLikeSizes($options)) {
            return 'Size';
        }

        if ($this->valuesLookLikeMaterials($options)) {
            return 'Material';
        }

        return ucwords(str_replace('_', ' ', $key));
    }

    private function isGenericKey(string $key): bool
    {
        $lower = strtolower($key);

        return (bool) preg_match('/^option\d+$/', $lower)
            || (bool) preg_match('/^property\d+$/', $lower)
            || in_array($lower, ['option', 'property', 'variante', 'variant', 'attr', 'attribute'], true);
    }

    private function valuesLookLikeSizes(array $values): bool
    {
        $patterns = [
            'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', '2xl', '3xl', '4xl', '5xl',
            'small', 'medium', 'large', 'x-large', 'xx-large',
            'petite', 'tall', 'plus', 'regular', 'slim', 'skinny', 'relaxed', 'loose',
            'one size', 'onesize', 'free', 'freesize', 'adjustable',
        ];

        $indicators = 0;
        foreach ($values as $v) {
            $lower = strtolower(trim((string) $v));
            if (in_array($lower, $patterns, true)) {
                $indicators++;
            } elseif (preg_match('/^\d{1,3}\s*(cm|mm|in|inch|inches|ft|feet|m|meter)$/', $lower)) {
                $indicators++;
            } elseif (preg_match('/^\d{1,3}$/', $lower) && (int) $lower >= 28 && (int) $lower <= 60) {
                $indicators++;
            }
        }

        return $indicators > 0 && $indicators >= count($values) * 0.4;
    }

    private function valuesLookLikeMaterials(array $values): bool
    {
        $patterns = [
            'cotton', 'polyester', 'nylon', 'wool', 'silk', 'leather', 'denim', 'linen',
            'spandex', 'elastane', 'acrylic', 'rayon', 'viscose', 'cashmere', 'velvet',
            'canvas', 'rubber', 'plastic', 'metal', 'wood', 'glass', 'ceramic', 'steel',
            'aluminum', 'copper', 'gold', 'silver', 'platinum', 'titanium', 'carbon fiber',
            'suede', 'fleece', 'jersey', 'chiffon', 'satin', 'lace', 'twill', 'oxford',
        ];

        $indicators = 0;
        foreach ($values as $v) {
            $lower = strtolower(trim((string) $v));
            foreach ($patterns as $p) {
                if (str_contains($lower, $p)) {
                    $indicators++;
                    break;
                }
            }
        }

        return $indicators > 0 && $indicators >= count($values) * 0.4;
    }

    private const COLOR_NAMES = [
        'black' => true, 'white' => true, 'red' => true, 'blue' => true, 'green' => true,
        'yellow' => true, 'orange' => true, 'purple' => true, 'pink' => true, 'brown' => true,
        'gray' => true, 'grey' => true, 'beige' => true, 'cream' => true, 'navy' => true,
        'maroon' => true, 'teal' => true, 'gold' => true, 'silver' => true, 'khaki' => true,
        'turquoise' => true, 'coral' => true, 'burgundy' => true, 'charcoal' => true,
        'nude' => true, 'camel' => true, 'olive' => true, 'mint' => true, 'lavender' => true,
        'peach' => true, 'wine' => true, 'rose' => true, 'lilac' => true, 'taupe' => true,
        'ivory' => true, 'magenta' => true, 'cyan' => true, 'indigo' => true, 'violet' => true,
        'plum' => true, 'mustard' => true, 'rust' => true, 'copper' => true, 'bronze' => true,
        'multicolor' => true, 'multi' => true, 'clear' => true, 'transparent' => true,
    ];
}
