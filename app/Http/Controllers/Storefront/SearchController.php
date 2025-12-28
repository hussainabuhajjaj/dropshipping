<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    use TransformsProducts;

    public function __invoke(Request $request): Response
    {
        $query = $request->query('q');
        $perPage = 18;

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($query) {
            $productQuery->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
                $builder->orWhereHas('category', function ($categoryBuilder) use ($query) {
                    $categoryBuilder->where('name', 'like', '%' . $query . '%');
                });
            });
        }

        $results = $productQuery
            ->latest()
            ->paginate($perPage)
            ->through(fn (Product $product) => $this->transformProduct($product));

        return Inertia::render('Search', [
            'results' => $results,
            'query' => $query,
            'currency' => 'USD',
            'filters' => [
                'q' => $query,
                'page' => $results->currentPage(),
            ],
        ]);
    }
}
