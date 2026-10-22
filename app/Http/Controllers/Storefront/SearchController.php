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

        $hasCombinedFulltext = $this->fulltextWorks();

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
                        ->orWhere('products.searchable_text', 'like', '%' . $query . '%')
                        ->orWhereHas('variants', function (Builder $variantBuilder) use ($query) {
                            $variantBuilder
                                ->where('sku', 'like', '%' . $query . '%')
                                ->orWhere('title', 'like', '%' . $query . '%');
                        });
                })
                ->orderByRaw('CASE 
                    WHEN name LIKE ? THEN 5
                    WHEN name LIKE ? THEN 3
                    WHEN code = ? THEN 4
                    WHEN description LIKE ? THEN 1
                    WHEN products.searchable_text LIKE ? THEN 1
                    ELSE 0
                END + CASE WHEN products.stock_on_hand > 0 THEN 2 ELSE 0 END DESC', [$query . '%', '%' . $query . '%', $query, '%' . $query . '%', '%' . $query . '%'])
                ->latest();

            // Build fulltext query only if the combined index exists
            if ($hasCombinedFulltext) {
                $fulltextQuery = clone $baseQuery;
                $fulltextQuery
                    ->select('products.*')
                    ->selectRaw('MATCH(products.name, products.description, products.code, products.meta_title, products.searchable_text) AGAINST (? IN BOOLEAN MODE) as search_relevance', [$booleanQuery])
                    ->where(function (Builder $builder) use ($booleanQuery, $query) {
                        $builder
                            ->whereRaw('MATCH(products.name, products.description, products.code, products.meta_title, products.searchable_text) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
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
                    ->orderByDesc('products.stock_on_hand')
                    ->latest();
                $usedFulltext = true;
            }
        } else {
            $fallbackQuery->latest();
        }

        $suggestion = null;

        if ($useTypesense && $query !== '') {
            try {
                $results = app(TypesenseSearchService::class)
                    ->search($query, $perPage, (int) $request->query('page', 1))
                    ->through(fn (Product $product) => $this->transformProduct($product));

                // If Typesense returns too few results, enrich with DB fallback
                if ($results->total() < 3) {
                    if ($hasCombinedFulltext) {
                        $results = ($fulltextQuery ?? $fallbackQuery)
                            ->paginate($perPage)
                            ->through(fn (Product $product) => $this->transformProduct($product));
                    }
                    if ($results->isEmpty() || $results->total() < 3) {
                        $results = $fallbackQuery
                            ->paginate($perPage)
                            ->through(fn (Product $product) => $this->transformProduct($product));
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $results = ($fulltextQuery ?? $fallbackQuery)
                    ->paginate($perPage)
                    ->through(fn (Product $product) => $this->transformProduct($product));
                if ($results->isEmpty()) {
                    $results = $fallbackQuery
                        ->paginate($perPage)
                        ->through(fn (Product $product) => $this->transformProduct($product));
                }
            }
        } else {
            try {
                $results = ($fulltextQuery ?? $fallbackQuery)
                    ->paginate($perPage)
                    ->through(fn (Product $product) => $this->transformProduct($product));

                // If FULLTEXT gave zero hits, retry with LIKE fallback for better recall
                if ($usedFulltext && $results->isEmpty()) {
                    $results = $fallbackQuery
                        ->paginate($perPage)
                        ->through(fn (Product $product) => $this->transformProduct($product));
                }
            } catch (\Illuminate\Database\QueryException $e) {
                $errCode = $e->errorInfo[1] ?? null;
                if ($errCode === 1191 || str_contains($e->getMessage(), '1191')) {
                    $results = $fallbackQuery
                        ->paginate($perPage)
                        ->through(fn (Product $product) => $this->transformProduct($product));
                } else {
                    throw $e;
                }
            }
        }

        // If everything returned zero results, try typo-tolerant enhanced search
        if ($query !== '' && $results->isEmpty()) {
            $results = $this->performEnhancedSearch($query, $perPage);
            if ($results->isNotEmpty()) {
                $suggestion = $this->suggestDidYouMean($query);
            }
        }

        return Inertia::render('Search', [
            'results' => $results,
            'query' => $query,
            'currency' => 'USD',
            'suggestion' => $suggestion,
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
                                ->orWhere('description', 'like', '%' . $query . '%')
                                ->orWhere('products.searchable_text', 'like', '%' . $query . '%');
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
                    ->orWhere('description', 'like', '%' . $query . '%')
                    ->orWhere('products.searchable_text', 'like', '%' . $query . '%');
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
                $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $term);
                return $normalized ?: $term;
            })
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
     * Generate typo-tolerant search variations (French-friendly)
     */
    private function generateTypoVariations(string $query): array
    {
        $variations = [$query];
        $lower = mb_strtolower($query);

        // Accent-free variant (ASCII transliteration)
        $noAccents = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $query);
        if ($noAccents !== $query) {
            $variations[] = $noAccents;
        }

        // French common misspellings and phonetic equivalents
        $commonTypos = [
            'chaussure' => ['chaussur', 'chausure', 'chausur', 'saussure'],
            'chaussures' => ['chaussur', 'chausure', 'chausur', 'saussures'],
            'téléphone' => ['telephone', 'telefone', 'telphone', 'telepone'],
            'ordinateur' => ['ordinatuer', 'ordi', 'ordinateur'],
            'ordinateurs' => ['ordinatuers', 'ordis', 'ordinateur'],
            'écran' => ['ecran', 'ecrant', 'écrant'],
            'écrans' => ['ecrans', 'ecrant', 'écrant'],
            'chaussette' => ['chausset', 'chausette', 'chausete'],
            'chaussettes' => ['chausset', 'chausettes', 'chausetes'],
            'sac' => ['sac', 'sak', 'sache'],
            'sacs' => ['sak', 'sacs', 'sache'],
            'montre' => ['montr', 'montres', 'montré'],
            'montres' => ['montr', 'montre', 'montré'],
            'collier' => ['colier', 'colye', 'kollier'],
            'colliers' => ['coliers', 'colye', 'kolliers'],
            'chemise' => ['chemis', 'chemises', 'chemize'],
            'chemises' => ['chemis', 'chemise', 'chemize'],
            'veste' => ['vest', 'vestes', 'vèste'],
            'vestes' => ['vest', 'veste', 'vèste'],
            'pantalon' => ['pantalons', 'pantallon', 'pentalon'],
            'pantalons' => ['pantalon', 'pantallons', 'pentalons'],
            'bijou' => ['bijoux', 'bijeau', 'bizou'],
            'bijoux' => ['bijou', 'bijeaux', 'bizoux'],
            'robe' => ['robes', 'robbe', 'robes'],
            'robes' => ['robe', 'robbes', 'robe'],
            'jupe' => ['jupes', 'jupe', 'juppe'],
            'jupes' => ['jupe', 'jupes', 'juppes'],
        ];

        foreach ($commonTypos as $correct => $typos) {
            if (str_contains($lower, $correct)) {
                foreach ($typos as $typo) {
                    $variations[] = str_ireplace($correct, $typo, $query);
                }
            }
        }

        // Strip repeated/duplicate words
        $words = preg_split('/\s+/', $query) ?: [];
        if (count($words) > 1) {
            $deduped = implode(' ', array_unique($words));
            if ($deduped !== $query) {
                $variations[] = $deduped;
            }
        }

        return array_values(array_unique($variations));
    }

    /**
     * Enhanced search with typo tolerance — tries variations when original query yields zero results.
     */
    private function performEnhancedSearch(string $query, int $perPage = 18): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $variations = $this->generateTypoVariations($query);

        foreach ($variations as $variation) {
            if ($variation === $query) {
                continue;
            }

            $booleanQuery = $this->toBooleanFullTextQuery($variation);
            $hasFulltext = $booleanQuery !== null && $this->fulltextWorks();

            $enhancedQuery = Product::query()
                ->where('is_active', true)
                ->with(['images', 'category', 'variants', 'translations'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews');

            if ($hasFulltext) {
                $enhancedQuery
                    ->select('products.*')
                    ->selectRaw('MATCH(products.name, products.description, products.code, products.meta_title, products.searchable_text) AGAINST (? IN BOOLEAN MODE) as search_relevance', [$booleanQuery])
                    ->where(function (Builder $builder) use ($booleanQuery, $variation) {
                        $builder
                            ->whereRaw('MATCH(products.name, products.description, products.code, products.meta_title, products.searchable_text) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                            ->orWhere('name', 'like', $variation . '%')
                            ->orWhere('name', 'like', '%' . $variation . '%');
                    })
                    ->orderByDesc('search_relevance')
                    ->orderByDesc('products.stock_on_hand')
                    ->latest();
            } else {
                $enhancedQuery
                    ->where(function (Builder $builder) use ($variation) {
                        $builder
                            ->where('name', 'like', $variation . '%')
                            ->orWhere('name', 'like', '%' . $variation . '%')
                            ->orWhere('searchable_text', 'like', '%' . $variation . '%');
                    })
                    ->orderByRaw('CASE WHEN name LIKE ? THEN 5 ELSE 0 END + CASE WHEN products.stock_on_hand > 0 THEN 2 ELSE 0 END DESC', [$variation . '%'])
                    ->latest();
            }

            $results = $enhancedQuery
                ->paginate($perPage)
                ->through(fn (Product $product) => $this->transformProduct($product));

            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        return Product::query()
            ->whereRaw('0 = 1')
            ->paginate($perPage)
            ->through(fn (Product $product) => $this->transformProduct($product));
    }

    /**
     * Suggest a corrected search query when zero results are found ("Did you mean?").
     */
    private function suggestDidYouMean(string $query): ?string
    {
        $lower = mb_strtolower(trim($query));
        if ($lower === '') {
            return null;
        }

        // Look up popular searches for nearby matches
        $popular = Cache::get('popular_searches', collect());

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($popular->keys() as $popularQuery) {
            $distance = levenshtein($lower, mb_strtolower($popularQuery), 1, 2, 2);
            if ($distance < $bestDistance && $distance <= 3) {
                $bestDistance = $distance;
                $best = $popularQuery;
            }
        }

        // If no match in popular searches, try matching against product names
        if ($best === null) {
            $closeSlugs = Product::query()
                ->where('is_active', true)
                ->select('name')
                ->limit(50)
                ->get()
                ->pluck('name');

            foreach ($closeSlugs as $name) {
                $distance = levenshtein($lower, mb_strtolower($name), 1, 2, 2);
                if ($distance < $bestDistance && $distance <= 3) {
                    $bestDistance = $distance;
                    $best = $name;
                }
            }
        }

        return $best;
    }

    private function fulltextWorks(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            DB::select("SELECT 1 FROM products WHERE MATCH(name, description, code, meta_title, searchable_text) AGAINST (? IN BOOLEAN MODE) LIMIT 1", ['healthcheck']);
            $cached = true;
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }
}
