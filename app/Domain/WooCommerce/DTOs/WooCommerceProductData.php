<?php

declare(strict_types=1);

namespace App\Domain\WooCommerce\DTOs;

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
        public readonly ?float $regularPrice = null,
        public readonly ?float $salePrice = null,
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
            'regularPrice' => $this->regularPrice,
            'salePrice' => $this->salePrice,
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
}
