<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $staticPaths = [
            '/',
            '/products',
            '/orders/track',
            '/support',
            '/faq',
            '/about',
            '/legal/shipping-policy',
            '/legal/refund-policy',
            '/legal/terms-of-service',
            '/legal/privacy-policy',
            '/legal/customs-disclaimer',
            '/legal/user-data-deletion',
        ];

        $urls = collect($staticPaths)->map(fn (string $path) => [
            'loc' => $baseUrl . $path,
            'lastmod' => now()->toAtomString(),
        ]);

        $categories = Category::query()
            ->select(['slug', 'updated_at'])
            ->whereNotNull('slug')
            ->get()
            ->map(fn (Category $category) => [
                'loc' => $baseUrl . '/categories/' . $category->slug,
                'lastmod' => $category->updated_at?->toAtomString(),
            ]);

        $products = Product::query()
            ->where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->whereNotNull('slug')
            ->get()
            ->map(fn (Product $product) => [
                'loc' => $baseUrl . '/products/' . $product->slug,
                'lastmod' => $product->updated_at?->toAtomString(),
            ]);

        $xml = view('seo.sitemap', [
            'urls' => $urls->merge($categories)->merge($products)->values()->all(),
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
