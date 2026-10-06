<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\DTOs;

use App\Services\AI\TranslationProvider;
use Livewire\Wireable;

class WooCommerceProductData implements Wireable
{
    public function __construct(
        public readonly int $woocommerceId,
        public readonly ?int $woocommerceVariationId = null,
        public readonly string $name = '',
        public readonly string $slug = '',
        public readonly string $sku = '',
        public readonly string $type = 'simple',
        public readonly string $status = 'publish',
        public readonly ?string $description = null,
        public readonly ?string $shortDescription = null,
        public readonly ?float $price = null,
        public readonly ?float $regularPrice = null,
        public readonly ?float $salePrice = null,
        public readonly string $currency = 'USD',
        public readonly ?float $weight = null,
        public readonly ?float $length = null,
        public readonly ?float $width = null,
        public readonly ?float $height = null,
        public readonly bool $manageStock = false,
        public readonly ?int $stockQuantity = null,
        public readonly string $stockStatus = 'instock',
        public readonly array $categories = [],
        public readonly array $images = [],
        public readonly array $attributes = [],
        public readonly array $variations = [],
        public readonly array $metaData = [],
        public readonly ?string $parentSku = null,
        public readonly array $rawData = [],
    ) {
    }

    public function toLivewire(): array
    {
        return [
            'woocommerceId' => $this->woocommerceId,
            'woocommerceVariationId' => $this->woocommerceVariationId,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
            'shortDescription' => $this->shortDescription,
            'price' => $this->price,
            'regularPrice' => $this->regularPrice,
            'salePrice' => $this->salePrice,
            'currency' => $this->currency,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'manageStock' => $this->manageStock,
            'stockQuantity' => $this->stockQuantity,
            'stockStatus' => $this->stockStatus,
            'categories' => $this->categories,
            'images' => $this->images,
            'attributes' => $this->attributes,
            'variations' => $this->variations,
            'metaData' => $this->metaData,
            'parentSku' => $this->parentSku,
        ];
    }

    public static function fromLivewire($value): self
    {
        return new self(...$value);
    }

    public function activePrice(): ?float
    {
        if ($this->price !== null && $this->price > 0) {
            return $this->price;
        }

        if ($this->salePrice !== null && $this->salePrice > 0) {
            return $this->salePrice;
        }

        if ($this->regularPrice !== null && $this->regularPrice > 0) {
            return $this->regularPrice;
        }

        return $this->price ?? $this->salePrice ?? $this->regularPrice;
    }

    public function compareAtPrice(): ?float
    {
        $activePrice = $this->activePrice();

        if ($activePrice !== null && $this->regularPrice !== null && $this->regularPrice > $activePrice) {
            return $this->regularPrice;
        }

        return null;
    }

    public function metaValue(string $key): mixed
    {
        foreach ($this->metaData as $meta) {
            if (is_array($meta) && ($meta['key'] ?? null) === $key) {
                return $meta['value'] ?? null;
            }
        }

        return null;
    }

    public function sourceUrl(): ?string
    {
        foreach (['_product_upload_source_url', '_pu_source_url', 'source_url'] as $key) {
            $value = $this->metaValue($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public function englishTitleCandidate(): ?string
    {
        foreach (['_yoast_wpseo_title', '_product_title', 'product_title'] as $key) {
            $value = $this->metaValue($key);
            if (is_string($value) && $this->isUsableEnglishTitle($value)) {
                return trim($value);
            }
        }

        if ($this->isUsableEnglishTitle($this->name)) {
            return trim($this->name);
        }

        return null;
    }

    public function importName(?TranslationProvider $translator = null): string
    {
        $candidate = $this->englishTitleCandidate();
        if ($candidate !== null) {
            return $candidate;
        }

        if ($translator !== null && $this->containsCjk($this->name)) {
            try {
                $translated = trim($translator->translate($this->name, 'zh', 'en'));
                if ($this->isUsableEnglishTitle($translated)) {
                    return $translated;
                }
            } catch (\Throwable) {
                // Fall through to a deterministic English fallback.
            }
        }

        return 'Imported WooCommerce Product ' . $this->woocommerceId;
    }

    public function containsCjk(string $value): bool
    {
        return preg_match('/[\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $value) === 1;
    }

    public function hasNonEnglishName(): bool
    {
        return $this->containsCjk($this->name);
    }

    private function isUsableEnglishTitle(string $value): bool
    {
        $value = trim($value);

        return $value !== ''
            && ! $this->containsCjk($value)
            && preg_match('/[A-Za-z]/', $value) === 1;
    }
}
