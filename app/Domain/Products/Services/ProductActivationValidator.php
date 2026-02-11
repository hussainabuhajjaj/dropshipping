<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

use App\Models\Product;

class ProductActivationValidator
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<int, string>
     */
    public function errorsForActivation(Product $product, array $overrides = []): array
    {
        $errors = [];

        $name = trim((string) ($overrides['name'] ?? $product->name ?? ''));
        if ($name === '') {
            $errors[] = 'Product name is required.';
        }

        $slug = trim((string) ($overrides['slug'] ?? $product->slug ?? ''));
        if ($slug === '') {
            $errors[] = 'Product slug is required.';
        }

        $categoryId = $overrides['category_id'] ?? $product->category_id;
        if (! is_numeric($categoryId) || (int) $categoryId <= 0) {
            $errors[] = 'Category is required.';
        }

        $selling = $overrides['selling_price'] ?? $product->selling_price;
        $cost = $overrides['cost_price'] ?? $product->cost_price;
        $pricing = PricingService::makeFromConfig();

        if (! is_numeric($selling) || (float) $selling <= 0) {
            $errors[] = 'Selling price must be greater than 0.';
        }

        if (! is_numeric($cost) || (float) $cost < 0) {
            $errors[] = 'Cost price is required for margin validation.';
        } elseif (is_numeric($selling)) {
            $min = $pricing->minSellingPrice((float) $cost);
            if ((float) $selling < $min) {
                $errors[] = "Selling price is below recommended minimum ({$min}).";
            }
        }

        $imagesCount = $product->exists
            ? ($product->relationLoaded('images') ? $product->images->count() : $product->images()->count())
            : 0;
        if ($imagesCount <= 0) {
            $errors[] = 'At least one product image is required.';
        }

        $variants = $product->exists
            ? ($product->relationLoaded('variants') ? $product->variants : $product->variants()->get(['id', 'title', 'price', 'cost_price']))
            : collect();

        if ($variants->isNotEmpty()) {
            $variantsWithoutPrice = $variants->filter(function ($variant): bool {
                return ! is_numeric($variant->price) || (float) $variant->price <= 0;
            })->count();

            if ($variantsWithoutPrice > 0) {
                $errors[] = "{$variantsWithoutPrice} variant(s) have missing or zero selling price.";
            }

            $variantBelowMargin = 0;
            foreach ($variants as $variant) {
                if (! is_numeric($variant->price) || (float) $variant->price <= 0) {
                    continue;
                }

                $variantCost = is_numeric($variant->cost_price)
                    ? (float) $variant->cost_price
                    : (is_numeric($cost) ? (float) $cost : null);

                if ($variantCost === null || $variantCost < 0) {
                    $variantBelowMargin++;
                    continue;
                }

                $variantMin = $pricing->minSellingPrice($variantCost);
                if ((float) $variant->price < $variantMin) {
                    $variantBelowMargin++;
                }
            }

            if ($variantBelowMargin > 0) {
                $errors[] = "{$variantBelowMargin} variant(s) are below recommended margin.";
            }
        }

        return array_values(array_unique($errors));
    }
}

