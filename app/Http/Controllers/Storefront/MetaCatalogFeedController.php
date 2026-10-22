<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Services\Storefront\HomeBuilderService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaCatalogFeedController extends Controller
{
    public function __invoke(HomeBuilderService $homeBuilder): StreamedResponse
    {
        $filename = 'meta-catalog-feed.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900, s-maxage=900',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ];

        $columns = [
            'id',
            'title',
            'description',
            'availability',
            'condition',
            'price',
            'sale_price',
            'link',
            'image_link',
            'brand',
            'google_product_category',
            'item_group_id',
        ];

        return response()->stream(function () use ($columns, $homeBuilder): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create feed output.');
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            Product::query()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->with([
                    'images' => fn ($query) => $query->orderBy('position'),
                    'category',
                    'translations',
                    'variants',
                ])
                ->orderBy('id')
                ->chunk(250, function ($products) use ($handle, $homeBuilder): void {
                    foreach ($products as $product) {
                        $row = $this->mapProduct($product, $homeBuilder);

                        if ($row === null) {
                            continue;
                        }

                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, Response::HTTP_OK, $headers);
    }

    /**
     * @return array<int, string>|null
     */
    private function mapProduct(Product $product, HomeBuilderService $homeBuilder): ?array
    {
        $primaryImage = $product->images->first()?->url;
        $imageLink = $homeBuilder->normalizeImage(is_string($primaryImage) ? $primaryImage : null);

        if ($imageLink === null || trim((string) $product->name) === '' || trim((string) $product->slug) === '') {
            return null;
        }

        $defaultVariant = $product->variants
            ->sortBy(fn ($variant) => $variant->price ?? PHP_FLOAT_MAX)
            ->first();

        $currentPrice = $defaultVariant?->price !== null
            ? (float) $defaultVariant->price
            : (float) ($product->selling_price ?? 0);

        if ($currentPrice <= 0) {
            return null;
        }

        $compareAt = $defaultVariant?->compare_at_price !== null
            ? (float) $defaultVariant->compare_at_price
            : null;

        $price = $compareAt !== null && $compareAt > $currentPrice
            ? $compareAt
            : $currentPrice;

        $salePrice = $compareAt !== null && $compareAt > $currentPrice
            ? $currentPrice
            : null;

        $stock = $product->variants->max(fn ($variant) => (int) ($variant->stock_on_hand ?? 0));
        if ($stock === null) {
            $stock = (int) ($product->stock_on_hand ?? 0);
        }

        $categoryName = $product->category?->name ?? 'Apparel & Accessories';
        $link = route('products.show', ['product' => $product->slug], true);
        $description = $this->sanitizeText($product->description ?: $product->meta_description ?: $product->name);

        return [
            (string) $product->id,
            $this->sanitizeText($product->name),
            $description,
            $stock > 0 ? 'in stock' : 'out of stock',
            'new',
            $this->formatPrice($price, $product->currency ?: 'USD'),
            $salePrice !== null ? $this->formatPrice($salePrice, $product->currency ?: 'USD') : '',
            $link,
            $imageLink,
            'Simbazu',
            $this->sanitizeText($categoryName),
            (string) $product->id,
        ];
    }

    private function formatPrice(float $amount, string $currency): string
    {
        return number_format($amount, 2, '.', '') . ' ' . strtoupper($currency ?: 'USD');
    }

    private function sanitizeText(?string $value): string
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value);
    }
}
