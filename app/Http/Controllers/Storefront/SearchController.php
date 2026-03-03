<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    use TransformsProducts;

    public function suggest(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'products' => [],
                'categories' => [],
            ]);
        }

        $productsLimit = max(1, min((int) $request->query('products_limit', 5), 10));
        $categoriesLimit = max(1, min((int) $request->query('categories_limit', 4), 10));
        $isMySql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $booleanQuery = $this->toBooleanFullTextQuery($query);

        $productsQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category']);

        if ($isMySql && $booleanQuery !== null) {
            $productsQuery
                ->select('products.*')
                ->selectRaw('MATCH(products.name, products.description) AGAINST (? IN BOOLEAN MODE) as search_relevance', [$booleanQuery])
                ->where(function (Builder $builder) use ($booleanQuery, $query) {
                    $builder
                        ->whereRaw('MATCH(products.name, products.description) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                        ->orWhere('name', 'like', '%' . $query . '%');
                })
                ->orderByDesc('search_relevance')
                ->latest();
        } else {
            $productsQuery
                ->where(function (Builder $builder) use ($query) {
                    $builder
                        ->where('name', 'like', '%' . $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%');
                })
                ->latest();
        }

        $categoriesQuery = Category::query()->active();
        if ($isMySql && $booleanQuery !== null) {
            $categoriesQuery
                ->where(function (Builder $builder) use ($booleanQuery, $query) {
                    $builder
                        ->whereRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                        ->orWhere('name', 'like', '%' . $query . '%')
                        ->orWhere('slug', 'like', '%' . $query . '%');
                })
                ->orderByRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE) desc', [$booleanQuery]);
        } else {
            $categoriesQuery->where(function (Builder $builder) use ($query) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhere('slug', 'like', '%' . $query . '%');
            });
        }

        $products = $productsQuery
            ->limit($productsLimit)
            ->get()
            ->map(function (Product $product) {
                $image = $product->images?->first()?->url;
                $category = $product->category;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $image,
                    'category' => $category?->name,
                    'href' => route('products.show', $product, false),
                ];
            })
            ->values();

        $categories = $categoriesQuery
            ->limit($categoriesLimit)
            ->get()
            ->map(function (Category $category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'href' => $category->slug
                        ? '/categories/' . urlencode((string) $category->slug)
                        : '/products?category=' . urlencode((string) $category->name),
                ];
            })
            ->values();

        return response()->json([
            'query' => $query,
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $perPage = 18;
        $isMySql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $booleanQuery = $this->toBooleanFullTextQuery($query);

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($query) {
            if ($isMySql && $booleanQuery !== null) {
                $productQuery
                    ->select('products.*')
                    ->selectRaw('MATCH(products.name, products.description) AGAINST (? IN BOOLEAN MODE) as search_relevance', [$booleanQuery])
                    ->where(function (Builder $builder) use ($booleanQuery, $query) {
                        $builder
                            ->whereRaw('MATCH(products.name, products.description) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                            ->orWhereHas('category', function (Builder $categoryBuilder) use ($booleanQuery, $query) {
                                $categoryBuilder
                                    ->whereRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                                    ->orWhere('name', 'like', '%' . $query . '%');
                            });
                    })
                    ->orderByDesc('search_relevance')
                    ->latest();
            } else {
                $productQuery->where(function (Builder $builder) use ($query) {
                    $builder
                        ->where('name', 'like', '%' . $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%');
                    $builder->orWhereHas('category', function (Builder $categoryBuilder) use ($query) {
                        $categoryBuilder->where('name', 'like', '%' . $query . '%');
                    });
                })->latest();
            }
        } else {
            $productQuery->latest();
        }

        $results = $productQuery
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

    private function toBooleanFullTextQuery(string $query): ?string
    {
        if ($query === '') {
            return null;
        }

        $terms = preg_split('/\s+/', $query) ?: [];
        $tokens = collect($terms)
            ->map(fn (string $term) => trim(preg_replace('/[^\pL\pN]+/u', '', $term) ?? ''))
            ->filter(fn (string $term) => mb_strlen($term) >= 2)
            ->unique()
            ->map(fn (string $term) => $term . '*')
            ->values()
            ->all();

        return $tokens === [] ? null : implode(' ', $tokens);
    }
}
