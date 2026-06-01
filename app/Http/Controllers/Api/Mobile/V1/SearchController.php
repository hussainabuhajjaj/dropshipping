<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Requests\Api\Mobile\V1\Search\SearchIndexRequest;
use App\Http\Resources\Mobile\V1\SearchResultResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SearchController extends ApiController
{
    public function index(SearchIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $locale = app()->getLocale();
        $query = $validated['q'] ?? null;
        $category = $validated['category'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $sort = $validated['sort'] ?? 'newest';
        $perPage = min((int) ($validated['per_page'] ?? 18), 50);
        $categoriesLimit = min((int) ($validated['categories_limit'] ?? 6), 20);
        $isMySql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $booleanQuery = $this->toBooleanFullTextQuery((string) ($query ?? ''));

        $productQuery = $this->buildProductQuery($query, $category, $minPrice, $maxPrice, $isMySql, $booleanQuery, $locale);
        $productQuery = $this->applyProductSort($productQuery, $sort);
        $products = $productQuery->paginate($perPage);

        // If zero results and query is non-empty, try enhanced search with typo tolerance
        if ($query && $products->isEmpty()) {
            $results = $this->performEnhancedSearch($query, $category, $minPrice, $maxPrice, $isMySql, $locale, $perPage, $sort);
            if ($results->isNotEmpty()) {
                $products = $results;
            }
        }

        $categoriesQuery = Category::query()
            ->active()
            ->withCount('products');

        if ($query) {
            if ($isMySql && $booleanQuery !== null) {
                $categoriesQuery
                    ->where(function (Builder $builder) use ($booleanQuery, $query, $locale) {
                        $builder
                            ->whereRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                            ->orWhere('name', 'like', '%' . $query . '%')
                            ->orWhere('slug', 'like', '%' . $query . '%')
                            ->orWhereHas('translations', function (Builder $translationBuilder) use ($query, $locale) {
                                $translationBuilder
                                    ->where('locale', $locale)
                                    ->where('name', 'like', '%' . $query . '%');
                            });
                    })
                    ->orderByRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE) desc', [$booleanQuery]);
            } else {
                $categoriesQuery->where(function (Builder $builder) use ($query, $locale) {
                    $builder
                        ->where('name', 'like', '%' . $query . '%')
                        ->orWhere('slug', 'like', '%' . $query . '%')
                        ->orWhereHas('translations', function (Builder $translationBuilder) use ($query, $locale) {
                            $translationBuilder
                                ->where('locale', $locale)
                                ->where('name', 'like', '%' . $query . '%');
                        });
                });
            }
        }

        $categories = $categoriesQuery
            ->orderByDesc('products_count')
            ->limit($categoriesLimit)
            ->get();

        return $this->success(
            new SearchResultResource([
                'query' => $query,
                'products' => $products->getCollection(),
                'categories' => $categories,
            ]),
            null,
            200,
            [
                'products' => [
                    'currentPage' => $products->currentPage(),
                    'lastPage' => $products->lastPage(),
                    'perPage' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'categories' => [
                    'total' => $categories->count(),
                ],
            ]
        );
    }

    private function buildProductQuery(
        ?string $query,
        ?string $category,
        mixed $minPrice,
        mixed $maxPrice,
        bool $isMySql,
        ?string $booleanQuery,
        string $locale
    ): Builder {
        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($category) {
            $productQuery->whereHas('category', function ($builder) use ($category, $locale) {
                $builder
                    ->where('name', $category)
                    ->orWhere('slug', $category)
                    ->orWhereHas('translations', function (Builder $translationBuilder) use ($category, $locale) {
                        $translationBuilder
                            ->where('locale', $locale)
                            ->where('name', $category);
                    });
            });
        }

        $minValue = $minPrice !== null && is_numeric($minPrice) ? (float) $minPrice : null;
        $maxValue = $maxPrice !== null && is_numeric($maxPrice) ? (float) $maxPrice : null;
        $productQuery->priceRange($minValue, $maxValue);

        if ($query) {
            if ($isMySql && $booleanQuery !== null) {
                $productQuery
                    ->select('products.*')
                    ->selectRaw('MATCH(products.name, products.description, products.code, products.meta_title, products.searchable_text) AGAINST (? IN BOOLEAN MODE) as search_relevance', [$booleanQuery])
                    ->where(function (Builder $builder) use ($booleanQuery, $query, $locale) {
                        $builder
                            ->whereRaw('MATCH(products.name, products.description, products.code, products.meta_title, products.searchable_text) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                            ->orWhereHas('translations', function (Builder $translationBuilder) use ($query, $locale) {
                                $translationBuilder
                                    ->where('locale', $locale)
                                    ->where(function (Builder $translated) use ($query) {
                                        $translated
                                            ->where('name', 'like', '%' . $query . '%')
                                            ->orWhere('description', 'like', '%' . $query . '%');
                                    });
                            })
                            ->orWhereHas('category', function (Builder $categoryBuilder) use ($booleanQuery, $query, $locale) {
                                $categoryBuilder
                                    ->whereRaw('MATCH(name) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery])
                                    ->orWhere('name', 'like', '%' . $query . '%')
                                    ->orWhereHas('translations', function (Builder $translationBuilder) use ($query, $locale) {
                                        $translationBuilder
                                            ->where('locale', $locale)
                                            ->where('name', 'like', '%' . $query . '%');
                                    });
                            });
                    });
            } else {
                $productQuery->where(function (Builder $builder) use ($query, $locale) {
                    $builder
                        ->where('name', 'like', '%' . $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%')
                        ->orWhere('products.searchable_text', 'like', '%' . $query . '%');
                    $builder->orWhereHas('translations', function (Builder $translationBuilder) use ($query, $locale) {
                        $translationBuilder
                            ->where('locale', $locale)
                            ->where(function (Builder $translated) use ($query) {
                                $translated
                                    ->where('name', 'like', '%' . $query . '%')
                                    ->orWhere('description', 'like', '%' . $query . '%');
                            });
                    });
                    $builder->orWhereHas('category', function (Builder $categoryBuilder) use ($query, $locale) {
                        $categoryBuilder
                            ->where('name', 'like', '%' . $query . '%')
                            ->orWhereHas('translations', function (Builder $translationBuilder) use ($query, $locale) {
                                $translationBuilder
                                    ->where('locale', $locale)
                                    ->where('name', 'like', '%' . $query . '%');
                            });
                    });
                })
                ->orderByRaw('CASE WHEN products.stock_on_hand > 0 THEN 2 ELSE 0 END DESC');
            }
        }

        return $productQuery;
    }

    private function applyProductSort(Builder $productQuery, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $productQuery
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) asc'),
            'price_desc' => $productQuery
                ->withMin('variants', 'price')
                ->orderByRaw('COALESCE(variants_min_price, selling_price) desc'),
            'rating' => $productQuery->orderByDesc('reviews_avg_rating'),
            'popular' => $productQuery->orderByDesc('reviews_count'),
            default => $this->applyDefaultSort($productQuery),
        };
    }

    private function applyDefaultSort(Builder $productQuery): Builder
    {
        $query = $productQuery->getQuery();
        $hasRelevance = collect($query->columns ?? [])->contains(
            fn ($column) => is_string($column) && str_contains(strtolower($column), 'search_relevance')
        );

        if ($hasRelevance) {
            return $productQuery->orderByDesc('search_relevance')->latest();
        }

        return $productQuery->latest();
    }

    private function toBooleanFullTextQuery(string $query): ?string
    {
        $query = trim($query);
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
                // Normalize accented characters for FULLTEXT matching
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
     * Perform enhanced search with typo tolerance when initial query yields zero results.
     */
    private function performEnhancedSearch(
        string $query,
        ?string $category,
        mixed $minPrice,
        mixed $maxPrice,
        bool $isMySql,
        string $locale,
        int $perPage,
        string $sort
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $variations = $this->generateTypoVariations($query);

        foreach ($variations as $variation) {
            if ($variation === $query) {
                continue;
            }

            $booleanQuery = $this->toBooleanFullTextQuery($variation);
            $productQuery = $this->buildProductQuery($variation, $category, $minPrice, $maxPrice, $isMySql, $booleanQuery, $locale);
            $productQuery = $this->applyProductSort($productQuery, $sort);

            $results = $productQuery->paginate($perPage);

            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        return Product::query()->whereRaw('0 = 1')->paginate($perPage);
    }

    /**
     * Generate typo-tolerant search variations (shared with Storefront SearchController).
     */
    private function generateTypoVariations(string $query): array
    {
        $variations = [$query];
        $lower = mb_strtolower($query);

        // Accent-free variant
        $noAccents = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $query);
        if ($noAccents !== $query) {
            $variations[] = $noAccents;
        }

        $commonTypos = [
            'chaussure' => ['chaussur', 'chausure', 'chausur', 'saussure'],
            'chaussures' => ['chaussur', 'chausure', 'chausur', 'saussures'],
            'téléphone' => ['telephone', 'telefone', 'telphone', 'telepone'],
            'ordinateur' => ['ordinatuer', 'ordi', 'ordinateur'],
            'écran' => ['ecran', 'ecrant', 'écrant'],
            'chaussette' => ['chausset', 'chausette', 'chausete'],
            'sac' => ['sac', 'sak', 'sache'],
            'montre' => ['montr', 'montres', 'montré'],
            'collier' => ['colier', 'colye', 'kollier'],
            'chemise' => ['chemis', 'chemises', 'chemize'],
            'veste' => ['vest', 'vestes', 'vèste'],
            'pantalon' => ['pantalons', 'pantallon', 'pentalon'],
            'bijou' => ['bijoux', 'bijeau', 'bizou'],
            'robe' => ['robes', 'robbe', 'robes'],
            'jupe' => ['jupes', 'jupe', 'juppe'],
        ];

        foreach ($commonTypos as $correct => $typos) {
            if (str_contains($lower, $correct)) {
                foreach ($typos as $typo) {
                    $variations[] = str_ireplace($correct, $typo, $query);
                }
            }
        }

        return array_values(array_unique($variations));
    }
}
