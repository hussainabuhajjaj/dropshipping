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
        }

        return $this->finalize($state);
    }

    public function extractFromQuery(Builder $query, int $chunkSize = 250): array
    {
        $state = $this->emptyState();

        (clone $query)
            ->select(['id', 'attributes'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($products) use (&$state): void {
                foreach ($products as $product) {
                    $this->ingestProduct($state, $product);
                }
            });

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
            || str_contains($normalized, 'supplier_');
    }

    private function emptyState(): array
    {
        return [
            'attributeKeys' => [],
            'attributeOptions' => [],
            'brands' => [],
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

    private function finalize(array $state): array
    {
        $attributeDefs = [];

        foreach (array_keys($state['attributeKeys']) as $key) {
            $attributeDefs[] = [
                'key' => $key,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'options' => array_values(array_filter(array_keys($state['attributeOptions'][$key] ?? []), fn ($v) => $v !== '')),
            ];
        }

        $brands = array_values(array_filter(array_keys($state['brands']), fn ($v) => $v !== ''));
        if (empty($brands)) {
            $brands = null;
        }

        return [
            'attributeDefs' => $attributeDefs,
            'brands' => $brands,
        ];
    }
}
