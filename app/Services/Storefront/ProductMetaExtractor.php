<?php

namespace App\Services\Storefront;

use App\Models\Product;

class ProductMetaExtractor
{
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
                    // Remove brand and CJ fields from attributes
                    if (in_array($key, ['brand', 'cj_pid', 'cj_last_payload', 'cj_last_changed_fields', 'cj_payload', 'cjpid', 'cj'])) continue;
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
}
