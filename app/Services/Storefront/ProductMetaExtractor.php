<?php

namespace App\Services\Storefront;

use App\Models\Product;

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
        $attributeKeys = [];
        $attributeOptions = [];
        $attributeDefs = [];
        $brands = [];

        foreach ($products as $product) {
            try {
                $attrs = $product->attributes;
                if (!is_array($attrs)) {
                    $attrs = json_decode($attrs, true);
                    if (!is_array($attrs)) {
                        $attrs = [];
                    }
                }
                if (!is_array($attrs)) continue;
                foreach ($attrs as $key => $value) {
                    if ($this->shouldSkipAttributeKey($key)) continue;
                    $attributeKeys[$key] = true;
                    if (!isset($attributeOptions[$key])) $attributeOptions[$key] = [];
                    if (is_string($value)) {
                        $attributeOptions[$key][$value] = true;
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
                                $attributeOptions[$key][$v] = true;
                            }
                        }
                    }
                    // else: skip if not string or flat array of strings
                }
            } catch (\Throwable $e) {
                // skip any errors in attribute extraction
                continue;
            }
        }

        foreach (array_keys($attributeKeys) as $key) {
            $attributeDefs[] = [
                'key' => $key,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'options' => array_values(array_filter(array_keys($attributeOptions[$key]), fn($v) => $v !== '')),
            ];
        }

        $brands = array_values(array_filter(array_keys($brands), fn($v) => $v !== ''));
        if (empty($brands)) {
            $brands = null;
        }

        return [
            'attributeDefs' => $attributeDefs,
            'brands' => $brands,
        ];
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
            || str_contains($normalized, 'supplier_');
    }
}
