<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\TransformsProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use App\Services\Search\TypesenseSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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

        // No caching or keyword logging; compute fresh each time
        $results = $this->performSearchSuggestion($query, $productsLimit, $categoriesLimit);

        return response()->json($results);
    }

    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $perPage = 18;
        $isMySql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $booleanQuery = $this->toBooleanFullTextQuery($query);
        $useTypesense = config('typesense.enabled');

        // Detect if a combined FULLTEXT index (name + description) exists
        $hasCombinedFulltext = false;
        if ($isMySql && $booleanQuery !== null) {
            try {
                $indexes = DB::select("SHOW INDEX FROM products WHERE Index_type = 'FULLTEXT'");
                $indexColumns = [];
                foreach ($indexes as $index) {
                    $indexColumns[$index->Key_name][] = $index->Column_name;
                }
                foreach ($indexColumns as $columns) {
                    if (count($columns) >= 2 && in_array('name', $columns, true) && in_array('description', $columns, true)) {
                        $hasCombinedFulltext = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $hasCombinedFulltext = false;
            }
        }

        $baseQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $fulltextQuery = null;
        $fallbackQuery = clone $baseQuery;
        $usedFulltext = false;

        if ($query !== '') {
            // Build fallback (LIKE) query
            $fallbackQuery
                ->where(function (Builder $builder) use ($query) {
                    $builder
                        ->where('name', 'like', $query . '%')
                        ->orWhere('name', 'like', '%' . $query . '%')
                        ->orWhere('code', 'like', '%' . $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%')
                        ->orWhereHas('variants', function (Builder $variantBuilder) use ($query) {
                            $variantBuilder
                                ->where('sku', 'like', '%' . $query . '%')
                                ->orWhere('title', 'like', '%' . $query . '%');
                        });
                })
                ->orderByRaw('CASE 
                    WHEN name LIKE ? THEN 5
                    WHEN name LIKE ? THEN 3
                    WHEN description LIKE ? THEN 1
                    ELSE 0
                END DESC', [$query . '%', '%' . $query . '%', '%' . $query . '%'])
                ->latest();

            // Build fulltext query only if the combined index exists
            if ($hasCombinedFulltext) {
                $fulltextQuery = clone $baseQuery;
                $fulltextQuery
                    ->select('products.*')
                    ->selectRaw('MATCH(products.name, products.description) AGAINST (? IN BOOLEAN MODE) as search_relevance', [$booleanQuery])
                    ->where(function (Builder $builder) use ($booleanQuery, $query) {
                        $builder
                            ->whereRaw('MATCH(products.name, products.description) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                            ->orWhere('name', 'like', $query . '%')
                            ->orWhere('name', 'like', '%' . $query . '%')
                            ->orWhere('code', 'like', '%' . $query . '%')
                            ->orWhereHas('variants', function (Builder $variantBuilder) use ($query) {
                                $variantBuilder
                                    ->where('sku', 'like', '%' . $query . '%')
                                    ->orWhere('title', 'like', '%' . $query . '%');
                            });
                    })
                    ->orderByDesc('search_relevance')
                    ->orderByRaw('name LIKE ? DESC', [$query . '%'])
                    ->orderByRaw('name LIKE ? DESC', ['%' . $query . '%'])
                    ->latest();
                $usedFulltext = true;
            }
        } else {
            $fallbackQuery->latest();
        }

        if ($useTypesense && $query !== '') {
            try {
                $results = app(TypesenseSearchService::class)
                    ->search($query, $perPage, (int) $request->query('page', 1))
                    ->through(fn (Product $product) => $this->transformProduct($product));

                // If Typesense returns too few results, enrich with DB fallback for better recall
                if ($results->total() < 3) {
                    $results = $fallbackQuery
                        ->paginate($perPage)
                        ->through(fn (Product $product) => $this->transformProduct($product));
                }
            } catch (\Throwable $e) {
                report($e);
                $results = $fallbackQuery
                    ->paginate($perPage)
                    ->through(fn (Product $product) => $this->transformProduct($product));
            }
        } else {
            try {
                $results = ($fulltextQuery ?? $fallbackQuery)
                    ->paginate($perPage)
                    ->through(fn (Product $product) => $this->transformProduct($product));

                // If we used FULLTEXT but got zero hits, retry with LIKE fallback for better recall
                if ($usedFulltext && $results->isEmpty()) {
                    $results = $fallbackQuery
                        ->paginate($perPage)
                        ->through(fn (Product $product) => $this->transformProduct($product));
                }
            } catch (\Illuminate\Database\QueryException $e) {
                $errCode = $e->errorInfo[1] ?? null; // MySQL driver error code
                if ($errCode === 1191 || str_contains($e->getMessage(), '1191')) {
                    // Retry with fallback LIKE query only
                    $results = $fallbackQuery
                        ->paginate($perPage)
                        ->through(fn (Product $product) => $this->transformProduct($product));
                } else {
                    throw $e;
                }
            }
        }

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

    private function trackSearchAnalytics(Request $request, string $query, string $type): void
    {
        // Log to database for analytics
        SearchLog::create([
            'query' => $query,
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'results_count' => 0, // Will be updated after search
        ]);
        
        // Track popular searches in cache
        $popularKey = 'popular_searches';
        $popular = Cache::get($popularKey, collect());

        // Work on plain array to avoid indirect modification on Collection
        $popularArray = $popular instanceof \Illuminate\Support\Collection
            ? $popular->toArray()
            : (array) $popular;

        $popularArray[$query] = ($popularArray[$query] ?? 0) + 1;

        // Keep only top 100 popular searches
        $sorted = collect($popularArray)->sortDesc()->take(100);
        Cache::put($popularKey, $sorted, 3600);
    }
    
    private function performSearchSuggestion(string $query, int $productsLimit = 5, int $categoriesLimit = 4): array
    {
        if (config('typesense.enabled')) {
            try {
                $results = app(\App\Services\Search\TypesenseSearchService::class)
                    ->search($query, $productsLimit, 1);

                $products = collect($results->items())
                    ->map(function (Product $product) {
                        $data = $this->transformProduct($product);
                        // Provide explicit URL field for suggestion click-through
                        if (! isset($data['url'])) {
                            $data['url'] = $data['href'] ?? route('products.show', $product, false);
                        }
                        return $data;
                    })
                    ->values();

                // If too few suggestions, backfill from DB fallback
                if ($products->count() < $productsLimit) {
                    $dbProducts = Product::query()
                        ->where('is_active', true)
                        ->with(['images', 'category'])
                        ->where(function (Builder $builder) use ($query) {
                            $builder
                                ->where('name', 'like', $query . '%')
                                ->orWhere('name', 'like', '%' . $query . '%')
                                ->orWhere('code', 'like', '%' . $query . '%')
                                ->orWhere('description', 'like', '%' . $query . '%');
                        })
                        ->orderByRaw('name LIKE ? DESC', [$query . '%'])
                        ->limit($productsLimit - $products->count())
                        ->get()
                        ->map(fn (Product $product) => $this->transformProduct($product));

                    $products = $products->merge($dbProducts)->take($productsLimit)->values();
                }

                // Also return top matching categories for quick navigation
                $categories = Category::query()
                    ->where('is_active', true)
                    ->where(function (Builder $builder) use ($query) {
                        $builder
                            ->where('name', 'like', $query . '%')
                            ->orWhere('name', 'like', '%' . $query . '%');
                    })
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

                return [
                    'query' => $query,
                    'products' => $products,
                    'categories' => $categories,
                ];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $products = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category'])
            ->where(function (Builder $builder) use ($query) {
                $builder
                    ->where('name', 'like', $query . '%')
                    ->orWhere('name', 'like', '%' . $query . '%')
                    ->orWhere('code', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->orderByRaw('name LIKE ? DESC', [$query . '%'])
            ->limit($productsLimit)
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product))
            ->values();

        $categories = Category::query()
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($query) {
                $builder
                    ->where('name', 'like', $query . '%')
                    ->orWhere('name', 'like', '%' . $query . '%');
            })
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

        return [
            'query' => $query,
            'products' => $products,
            'categories' => $categories,
        ];
    }

    public function getPopularSearches(): JsonResponse
    {
        $popular = Cache::get('popular_searches', collect());
        
        return response()->json([
            'popular_searches' => $popular->take(10)->map(function ($count, $query) {
                return [
                    'query' => $query,
                    'count' => $count,
                    'href' => '/search?q=' . urlencode($query),
                ];
            })->values()
        ]);
    }
    
    private function toBooleanFullTextQuery(string $query): ?string
    {
        if ($query === '') {
            return null;
        }

        // Enhanced token processing with better precision
        $terms = preg_split('/\s+/', $query) ?: [];
        $tokens = collect($terms)
            ->map(fn (string $term) => trim(preg_replace('/[^\pL\pN\-\+]/u', '', $term) ?? ''))
            ->filter(fn (string $term) => mb_strlen($term) >= 2)
            ->unique()
            ->map(function (string $term) {
                // Handle exact phrases with quotes
                if (str_starts_with($term, '"') && str_ends_with($term, '"')) {
                    return trim($term, '"');
                }
                // Handle exclusion with minus
                if (str_starts_with($term, '-')) {
                    return '-' . trim(ltrim($term, '-')) . '*';
                }
                // Handle inclusion with plus
                if (str_starts_with($term, '+')) {
                    return '+' . trim(ltrim($term, '+')) . '*';
                }
                return $term . '*';
            })
            ->values()
            ->all();

        return $tokens === [] ? null : implode(' ', $tokens);
    }
    
    /**
     * Generate typo-tolerant search variations
     */
    private function generateTypoVariations(string $query): array
    {
        $variations = [$query];
        
        // Common misspellings and variations
        $commonTypos = [
            'shoe' => ['shoes', 'shoo', 'shue'],
            'shirt' => ['shrt', 'shrit', 'shert'],
            'pants' => ['pant', 'pents', 'pans'],
            'dress' => ['dres', 'dreses', 'dres'],
            // Add more common typos as needed
        ];
        
        foreach ($commonTypos as $correct => $typos) {
            if (str_contains(strtolower($query), $correct)) {
                foreach ($typos as $typo) {
                    $variations[] = str_replace($correct, $typo, $query);
                }
            }
        }
        
        return array_unique($variations);
    }
    
    /**
     * Enhanced search with typo tolerance
     */
    private function performEnhancedSearch(string $query, int $limit = 5): array
    {
        $results = [];
        $variations = $this->generateTypoVariations($query);
        
        foreach ($variations as $variation) {
            if (count($results) >= $limit) break;
            
            $variationResults = $this->performSearchSuggestion($variation);
            
            // Add results that aren't already included
            foreach ($variationResults['products'] as $product) {
                if (!in_array($product['id'], array_column($results, 'id')) && count($results) < $limit) {
                    $results[] = $product;
                }
            }
        }
        
        return $results;
    }

    private function fulltextWorks(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            DB::select("SELECT 1 FROM products WHERE MATCH(name, description) AGAINST (? IN BOOLEAN MODE) LIMIT 1", ['healthcheck']);
            $cached = true;
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }
}
