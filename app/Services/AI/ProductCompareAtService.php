<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Domain\Products\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductCompareAtService
{
    private const MAX_MULTIPLIER = 3.0;

    public function __construct(private DeepSeekClient $client)
    {
    }

    public function generate(Product $product, bool $force = false): void
    {
        $product->loadMissing(['variants', 'category']);
        $variants = collect($product->variants ?? []);

        if ($variants->isEmpty()) {
            return;
        }

        $targets = $variants->filter(function ($variant) use ($force) {
            $price = $this->toNullableFloat($variant->price ?? null);
            if ($price === null || $price <= 0) {
                return false;
            }
            $compareAt = $this->toNullableFloat($variant->compare_at_price ?? null);
            if (! $force && $compareAt !== null && $compareAt > $price) {
                return false;
            }
            return true;
        });

        if ($targets->isEmpty()) {
            return;
        }

        $benchmarks = $this->buildBenchmarks($product->category_id);

        $context = [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'category_id' => $product->category_id,
                'selling_price' => $this->toNullableFloat($product->selling_price ?? null),
                'cost_price' => $this->toNullableFloat($product->cost_price ?? null),
                'currency' => $product->currency ?? 'USD',
            ],
            'variants' => $variants->map(function ($variant) use ($product) {
                return [
                    'id' => $variant->id,
                    'title' => $variant->title,
                    'price' => $this->toNullableFloat($variant->price ?? null),
                    'cost_price' => $this->toNullableFloat($variant->cost_price ?? null),
                    'compare_at_price' => $this->toNullableFloat($variant->compare_at_price ?? null),
                    'currency' => $variant->currency ?? $product->currency ?? 'USD',
                    'stock_on_hand' => $variant->stock_on_hand,
                ];
            })->values()->all(),
            'benchmarks' => $benchmarks,
        ];

        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $prompt = "Using the database pricing benchmarks and product data below, recommend a compare-at price (MSRP) for each variant.\n"
            . "Rules:\n"
            . "- compare_at_price must be higher than price.\n"
            . "- Keep discounts realistic; follow category/global benchmarks when available.\n"
            . "- If benchmarks are missing, default to a 10-25% discount range.\n"
            . "- Avoid extreme markups (> 3x price).\n"
            . "- Return JSON only, in the shape: {\"variants\":[{\"id\":123,\"compare_at_price\":49.99}]}\n\n"
            . "DATA:\n{$contextJson}";

        $content = $this->client->chat([
            [
                'role' => 'system',
                'content' => 'You are an ecommerce pricing assistant. Return ONLY valid JSON.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ], 0.2);

        $decoded = $this->parseJsonResponse($content);
        if (! is_array($decoded) || $decoded === []) {
            return;
        }

        $decisions = $this->normalizeDecisions($decoded, $targets);
        if ($decisions === []) {
            return;
        }

        $targetsById = $targets->keyBy('id');
        foreach ($decisions as $variantId => $compareAt) {
            if (! $targetsById->has($variantId)) {
                continue;
            }

            $variant = $targetsById->get($variantId);
            $price = (float) $variant->price;
            if ($compareAt <= $price) {
                continue;
            }
            if ($compareAt > $price * self::MAX_MULTIPLIER) {
                continue;
            }

            $variant->compare_at_price = $compareAt;

            $metadata = is_array($variant->metadata ?? null) ? $variant->metadata : [];
            $metadata['compare_at_ai'] = [
                'provider' => 'deepseek',
                'generated_at' => now()->toDateTimeString(),
                'category_id' => $product->category_id,
                'avg_discount_percent' => $benchmarks['category']['avg_discount_percent']
                    ?? $benchmarks['global']['avg_discount_percent']
                    ?? null,
            ];
            $variant->metadata = $metadata;
            $variant->save();
        }
    }

    private function buildBenchmarks(?int $categoryId): array
    {
        $baseQuery = ProductVariant::query()
            ->whereNotNull('compare_at_price')
            ->where('compare_at_price', '>', 0)
            ->where('price', '>', 0);

        $global = $this->statsForQuery($baseQuery);
        $category = null;

        if ($categoryId !== null) {
            $category = $this->statsForQuery(
                (clone $baseQuery)->whereHas('product', fn (Builder $query) => $query->where('category_id', $categoryId))
            );
        }

        return [
            'category' => $category,
            'global' => $global,
        ];
    }

    private function statsForQuery(Builder $query): array
    {
        $aggregate = (clone $query)->selectRaw(
            'AVG(price) as avg_price,
            AVG(compare_at_price) as avg_compare_at_price,
            AVG((compare_at_price - price) / compare_at_price * 100) as avg_discount_percent,
            MIN(price) as min_price,
            MAX(price) as max_price'
        )->first();

        $count = (clone $query)->count();

        $avgPrice = $this->toNullableFloat($aggregate?->avg_price ?? null);
        $avgCompare = $this->toNullableFloat($aggregate?->avg_compare_at_price ?? null);
        $avgDiscount = $this->toNullableFloat($aggregate?->avg_discount_percent ?? null);
        $minPrice = $this->toNullableFloat($aggregate?->min_price ?? null);
        $maxPrice = $this->toNullableFloat($aggregate?->max_price ?? null);

        $multiplier = null;
        if ($avgPrice !== null && $avgPrice > 0 && $avgCompare !== null) {
            $multiplier = round($avgCompare / $avgPrice, 3);
        }

        return [
            'sample_size' => $count,
            'avg_price' => $avgPrice !== null ? round($avgPrice, 2) : null,
            'avg_compare_at_price' => $avgCompare !== null ? round($avgCompare, 2) : null,
            'avg_discount_percent' => $avgDiscount !== null ? round($avgDiscount, 2) : null,
            'avg_compare_at_multiplier' => $multiplier,
            'min_price' => $minPrice !== null ? round($minPrice, 2) : null,
            'max_price' => $maxPrice !== null ? round($maxPrice, 2) : null,
        ];
    }

    /**
     * @return array<int, float>
     */
    private function normalizeDecisions(array $decoded, Collection $targets): array
    {
        $decisions = [];

        if (isset($decoded['variants']) && is_array($decoded['variants'])) {
            foreach ($decoded['variants'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = $row['id'] ?? $row['variant_id'] ?? null;
                $value = $row['compare_at_price'] ?? $row['compare_at'] ?? null;
                if ($id === null) {
                    continue;
                }
                $price = $this->normalizePrice($value);
                if ($price !== null) {
                    $decisions[(int) $id] = $price;
                }
            }
        } else {
            foreach ($decoded as $key => $value) {
                if (! is_numeric($key)) {
                    continue;
                }
                $price = $this->normalizePrice($value);
                if ($price !== null) {
                    $decisions[(int) $key] = $price;
                }
            }
        }

        if ($decisions === [] && $targets->count() === 1) {
            $only = $targets->first();
            $value = $decoded['compare_at_price'] ?? $decoded['compare_at'] ?? null;
            $price = $this->normalizePrice($value);
            if ($price !== null) {
                $decisions[(int) $only->id] = $price;
            }
        }

        return $decisions;
    }

    /**
     * Attempt to extract a JSON object from possibly noisy model output.
     *
     * @return array<string, mixed>|null
     */
    private function parseJsonResponse(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($sub, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizePrice(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $clean = preg_replace('/[^0-9.]/', '', $value);
            if ($clean === '') {
                return null;
            }
            $value = $clean;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if (! is_finite($number) || $number <= 0) {
            return null;
        }

        return round($number, 2);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }
}
