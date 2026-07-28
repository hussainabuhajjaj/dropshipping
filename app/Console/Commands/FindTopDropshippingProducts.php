<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Models\Product;
use App\Services\Pricing\PricingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class FindTopDropshippingProducts extends Command
{
    protected $signature = 'dropshipping:find-top-products 
        {--limit=10 : Number of top products to return}
        {--min-quality=70 : Minimum quality score (0-100)}
        {--min-margin=30 : Minimum margin percent}
        {--max-weight=500 : Maximum weight in grams}
        {--min-stock=5 : Minimum stock on hand}
        {--category= : Filter by category slug}
        {--output=table : Output format (table|json|csv)}
        {--save-to-file= : Save results to file path}';

    protected $description = 'Find top dropshipping products based on quality score, margin, weight, and stock';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $minQuality = (int) $this->option('min-quality');
        $minMargin = (int) $this->option('min-margin');
        $maxWeight = (int) $this->option('max-weight');
        $minStock = (int) $this->option('min-stock');
        $categorySlug = $this->option('category');
        $outputFormat = $this->option('output');
        $saveToFile = $this->option('save-to-file');

        $this->info('🔍 Finding top dropshipping products...');
        $this->newLine();

        $products = $this->queryTopProducts(
            limit: $limit,
            minQuality: $minQuality,
            minMargin: $minMargin,
            maxWeight: $maxWeight,
            minStock: $minStock,
            categorySlug: $categorySlug,
        );

        if ($products->isEmpty()) {
            $this->warn('No products found matching criteria.');
            return self::SUCCESS;
        }

        $this->displayResults($products, $outputFormat);

        if ($saveToFile) {
            $this->saveToFile($products, $saveToFile);
        }

        $this->newLine();
        $this->info("✅ Found {$products->count()} top products");

        return self::SUCCESS;
    }

    private function queryTopProducts(
        int $limit,
        int $minQuality,
        int $minMargin,
        int $maxWeight,
        int $minStock,
        ?string $categorySlug
    ): \Illuminate\Support\Collection {
        $query = Product::query()
            ->whereCjImported()
            ->where('is_active', true)
            ->with([
                'images' => fn ($q) => $q->orderBy('position'),
                'variants' => fn ($q) => $q->where('stock_on_hand', '>', 0),
                'category',
                'defaultWarehouse',
            ])
            ->withQualityScore();

        if ($categorySlug) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $products = $query->get();

        // Filter by quality score and other criteria
        $filtered = $products->filter(function (Product $product) use ($minQuality, $minMargin, $maxWeight, $minStock) {
            if (($product->quality_score ?? 0) < $minQuality) {
                return false;
            }

            $margin = $this->calculateMargin($product);
            if ($margin < $minMargin) {
                return false;
            }

            $weight = $this->getProductWeight($product);
            if ($weight > $maxWeight) {
                return false;
            }

            $stock = $product->cj_total_stock ?? $product->stock_on_hand ?? 0;
            if ($stock < $minStock) {
                return false;
            }

            return true;
        });

        // Score and sort
        $scored = $filtered->map(function (Product $product) {
            $margin = $this->calculateMargin($product);
            $weight = $this->getProductWeight($product);
            $qualityScore = $product->quality_score ?? 0;
            $stock = $product->cj_total_stock ?? $product->stock_on_hand ?? 0;

            // Composite score: quality (40%) + margin (30%) + weight inverse (20%) + stock (10%)
            $weightScore = max(0, 100 - ($weight / 10)); // lighter = better
            $stockScore = min(100, $stock * 2); // more stock = better

            $compositeScore = round(
                ($qualityScore * 0.4) +
                ($margin * 0.3) +
                ($weightScore * 0.2) +
                ($stockScore * 0.1),
                1
            );

            return [
                'product' => $product,
                'margin' => $margin,
                'weight' => $weight,
                'quality_score' => $qualityScore,
                'stock' => $stock,
                'composite_score' => $compositeScore,
                'estimated_shipping' => $this->estimateShipping($weight),
                'recommended_price' => $this->getRecommendedPrice($product, $margin),
            ];
        })->sortByDesc('composite_score')
        ->take($limit)
        ->values();

        return $scored;
    }

    private function calculateMargin(Product $product): float
    {
        $costPrice = (float) ($product->cost_price ?? 0);
        $sellingPrice = (float) ($product->selling_price ?? 0);

        if ($costPrice <= 0 || $sellingPrice <= 0) {
            return 0;
        }

        return round((($sellingPrice - $costPrice) / $sellingPrice) * 100, 1);
    }

    private function getProductWeight(Product $product): float
    {
        // Try variant metadata first
        foreach ($product->variants as $variant) {
            $meta = $variant->metadata ?? [];
            if (isset($meta['cj_variant']['variantWeight'])) {
                return (float) $meta['cj_variant']['variantWeight'];
            }
        }

        // Fallback to product attributes
        $attrs = $product->attributes ?? [];
        if (isset($attrs['cj_payload']['productWeight'])) {
            $weights = explode('-', (string) $attrs['cj_payload']['productWeight']);
            return (float) ($weights[count($weights) - 1] ?? 0);
        }

        // Default estimate for clothing
        return 300;
    }

    private function estimateShipping(float $weightGrams): float
    {
        // Rough CJ shipping estimate from China to US/EU
        // Base: $5 for <100g, then ~$0.50 per 100g
        if ($weightGrams <= 100) {
            return 5.0;
        }
        return round(5.0 + (($weightGrams - 100) / 100) * 0.5, 2);
    }

    private function getRecommendedPrice(Product $product, float $margin): float
    {
        $costPrice = (float) ($product->cost_price ?? 0);
        if ($costPrice <= 0) {
            return 0;
        }

        $targetMargin = $margin / 100;
        return round($costPrice / (1 - $targetMargin), 2);
    }

    private function displayResults(\Illuminate\Support\Collection $products, string $format): void
    {
        switch ($format) {
            case 'json':
                $this->output->write(json_encode($products, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $headers = ['Rank', 'ID', 'Name', 'Category', 'Cost', 'Selling', 'Margin%', 'Weight(g)', 'Quality', 'Stock', 'Composite', 'Est. Ship', 'Rec. Price'];
                $this->table($headers, $products->map(function ($item, $index) {
                    $p = $item['product'];
                    return [
                        $index + 1,
                        $p->id,
                        $p->name,
                        $p->category?->name ?? 'N/A',
                        '$' . number_format($p->cost_price ?? 0, 2),
                        '$' . number_format($p->selling_price ?? 0, 2),
                        $item['margin'] . '%',
                        $item['weight'] . 'g',
                        $item['quality_score'],
                        $item['stock'],
                        $item['composite_score'],
                        '$' . number_format($item['estimated_shipping'], 2),
                        '$' . number_format($item['recommended_price'], 2),
                    ];
                })->toArray());
                break;
            case 'table':
            default:
                $headers = ['Rank', 'ID', 'Name', 'Category', 'Cost', 'Price', 'Margin%', 'Weight', 'Quality', 'Stock', 'Score', 'Ship', 'Rec. Price'];
                $this->table($headers, $products->map(function ($item, $index) {
                    $p = $item['product'];
                    return [
                        $index + 1,
                        $p->id,
                        strlen($p->name) > 40 ? substr($p->name, 0, 37) . '...' : $p->name,
                        $p->category?->name ?? 'N/A',
                        '$' . number_format($p->cost_price ?? 0, 2),
                        '$' . number_format($p->selling_price ?? 0, 2),
                        $item['margin'] . '%',
                        $item['weight'] . 'g',
                        $item['quality_score'],
                        $item['stock'],
                        $item['composite_score'],
                        '$' . number_format($item['estimated_shipping'], 2),
                        '$' . number_format($item['recommended_price'], 2),
                    ];
                })->toArray());
        }
    }

    private function saveToFile(\Illuminate\Support\Collection $products, string $path): void
    {
        $data = $products->map(function ($item) {
            $p = $item['product'];
            return [
                'product_id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'category' => $p->category?->slug,
                'cost_price' => $p->cost_price,
                'selling_price' => $p->selling_price,
                'margin_percent' => $item['margin'],
                'weight_grams' => $item['weight'],
                'quality_score' => $item['quality_score'],
                'stock' => $item['stock'],
                'composite_score' => $item['composite_score'],
                'estimated_shipping' => $item['estimated_shipping'],
                'recommended_price' => $item['recommended_price'],
                'image_url' => $p->images->first()?->url,
                'cj_pid' => $p->cj_pid,
                'cj_vid' => $p->variants->first()?->metadata['cj_vid'] ?? null,
            ];
        });

        $content = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($path, $content);
        $this->info("💾 Results saved to {$path}");
    }
}