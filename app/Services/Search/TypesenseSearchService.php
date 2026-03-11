<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class TypesenseSearchService
{
    /**
     * Primary search entrypoint.
     */
    public function search(string $query, int $perPage, int $page): LengthAwarePaginator
    {
        $config = config('typesense');
        $node = $config['nearest_node'] ?? $config['nodes'][0];

        $expandedQuery = $this->expandQueryWithSynonyms($query);

        $endpoint = sprintf('%s://%s:%s/collections/%s/documents/search',
            $node['protocol'],
            $node['host'],
            $node['port'],
            $config['collection']
        );

            $response = Http::withHeaders([
                    'X-TYPESENSE-API-KEY' => $config['api_key'],
                    'Content-Type' => 'application/json',
                ])
                ->timeout($config['connection_timeout_seconds'] ?? 2)
                ->get($endpoint, [
                    'q' => $expandedQuery,
                    'query_by' => 'name,description,variants,tags,category_name,keywords',
                    'query_by_weights' => '8,2,3,2,1,3',
                    'filter_by' => 'is_active:=true',
                    'per_page' => $perPage,
                    'page' => $page,
                    'highlight_fields' => 'name,description',
                    'sort_by' => '_text_match(buckets:10):desc,popularity:desc,created_at:desc',
                    'text_match_type' => 'max_weight',
                    'num_typos' => 2,
                    'drop_tokens_threshold' => 1,
                    'prioritize_exact_match' => true,
                    'prioritize_token_position' => true,
                ]);

            // Retry with relaxed params if nothing found
            if (($response->json('found') ?? 0) === 0) {
                $response = Http::withHeaders([
                        'X-TYPESENSE-API-KEY' => $config['api_key'],
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout($config['connection_timeout_seconds'] ?? 2)
                    ->get($endpoint, [
                        'q' => $expandedQuery,
                        'query_by' => 'name,description,variants,tags,category_name,keywords',
                        'query_by_weights' => '8,2,3,2,1,3',
                        'filter_by' => 'is_active:=true',
                        'per_page' => $perPage,
                        'page' => $page,
                        'highlight_fields' => 'name,description',
                        'sort_by' => '_text_match:desc,created_at:desc',
                        'text_match_type' => 'sum_score',
                        'num_typos' => 2,
                        'drop_tokens_threshold' => 2,
                        'prioritize_exact_match' => false,
                        'prioritize_token_position' => true,
                    ]);
            }

        if ($response->failed()) {
            throw new \RuntimeException('Typesense search failed: ' . $response->body());
        }

        $hits = $response->json('hits', []);
        $total = (int) ($response->json('found') ?? 0);
        $productIds = collect($hits)
            ->map(fn ($hit) => Arr::get($hit, 'document.id'))
            ->filter()
            ->values()
            ->all();

        if (empty($productIds)) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with(['images', 'category', 'variants', 'translations'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $productIds)) . ')')
            ->get();

        return new LengthAwarePaginator(
            $products,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function upsert(Product $product): void
    {
        $this->ensureCollection();

        $doc = [
            'id' => (string) $product->id,
            'name' => (string) $product->name,
            'description' => (string) ($product->description ?? ''),
            'category_name' => (string) (optional($product->category)->name ?? ''),
            'category_id' => (string) (optional($product->category)->id ?? ''),
            'price' => (float) $product->price,
            'currency' => (string) ($product->currency ?? 'USD'),
            'rating' => (float) ($product->reviews_avg_rating ?? 0),
            'reviews_count' => (int) ($product->reviews_count ?? 0),
            'is_active' => (bool) $product->is_active,
            'created_at' => $product->created_at?->getTimestamp() ?? time(),
            'variants' => $product->variants->pluck('title')->filter()->values()->all(),
            'tags' => $product->tags ?? [],
            'keywords' => $this->buildKeywords($product),
            'popularity' => (float) ($product->popularity ?? 0),
        ];

        $resp = $this->client()->post($this->collectionPath('documents') . '?action=upsert', $doc);
        if (! $resp->successful()) {
            throw new \RuntimeException('Typesense upsert failed: ' . $resp->body());
        }
    }

    public function recreateCollection(): void
    {
        $config = config('typesense');
        $collection = $config['collection'];
        $client = $this->client();

        $client->delete('/collections/' . $collection);
        $this->ensureCollection(true);
    }

    public function delete(int|string $id): void
    {
        if (! config('typesense.enabled')) {
            return;
        }

        $this->client()->delete($this->collectionPath('documents/' . urlencode((string) $id)), []);
    }

    private function ensureCollection(bool $force = false): void
    {
        static $ensured = false;
        if ($ensured && ! $force) {
            return;
        }

        $config = config('typesense');
        $collection = $config['collection'];

        $client = $this->client();

        if (! $force) {
            $exists = $client->get('/collections/' . $collection);
            if ($exists->ok()) {
                $ensured = true;
                return;
            }
        }

        $schema = [
            'name' => $collection,
            'fields' => [
                ['name' => 'name', 'type' => 'string', 'infix' => true],
                ['name' => 'description', 'type' => 'string', 'infix' => true, 'optional' => true],
                ['name' => 'variants', 'type' => 'string[]', 'infix' => true, 'optional' => true],
                ['name' => 'tags', 'type' => 'string[]', 'facet' => true, 'optional' => true, 'infix' => true],
                ['name' => 'keywords', 'type' => 'string[]', 'facet' => true, 'optional' => true, 'infix' => true],
                ['name' => 'category_name', 'type' => 'string', 'facet' => true, 'optional' => true],
                ['name' => 'category_id', 'type' => 'string', 'facet' => true, 'optional' => true],
                ['name' => 'price', 'type' => 'float', 'sort' => true],
                ['name' => 'currency', 'type' => 'string', 'facet' => true],
                ['name' => 'rating', 'type' => 'float', 'sort' => true],
                ['name' => 'reviews_count', 'type' => 'int32', 'sort' => true],
                ['name' => 'popularity', 'type' => 'float', 'sort' => true, 'optional' => true],
                ['name' => 'is_active', 'type' => 'bool', 'facet' => true],
                ['name' => 'created_at', 'type' => 'int64', 'sort' => true],
            ],
            'default_sorting_field' => 'created_at',
            'enable_nested_fields' => false,
        ];

        $resp = $client->post('/collections', $schema);
        if (! $resp->successful()) {
            throw new \RuntimeException('Failed to create Typesense collection: ' . $resp->body());
        }

        $ensured = true;
    }

    private function collectionPath(string $suffix): string
    {
        return '/collections/' . config('typesense.collection') . '/' . ltrim($suffix, '/');
    }

    private function client()
    {
        $config = config('typesense');
        $node = $config['nearest_node'] ?? $config['nodes'][0];

        $base = sprintf('%s://%s:%s', $node['protocol'], $node['host'], $node['port']);

        return Http::withHeaders([
                'X-TYPESENSE-API-KEY' => $config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->baseUrl($base)
            ->timeout($config['connection_timeout_seconds'] ?? 2)
            ->acceptJson();
    }

    private function expandQueryWithSynonyms(string $query): string
    {
        $synonyms = [
            'tshirt' => ['t-shirt', 'tee', 't shirt', 'tshirts', 'tees'],
            'sneaker' => ['sneakers', 'trainer', 'trainers', 'running shoes'],
            'shoe' => ['shoes', 'footwear'],
        ];

        $terms = preg_split('/\s+/', strtolower(trim($query))) ?: [];
        $expanded = $terms;

        foreach ($terms as $term) {
            foreach ($synonyms as $key => $alts) {
                if ($term === $key || in_array($term, $alts, true)) {
                    $expanded = array_merge($expanded, [$key], $alts);
                }
            }
        }

        $expanded = array_values(array_unique(array_filter($expanded)));
        if (empty($expanded)) {
            return $query;
        }

        return implode(' ', $expanded);
    }

    private function buildKeywords(Product $product): array
    {
        $keywords = [];

        // From category
        if ($product->category) {
            $keywords[] = strtolower((string) $product->category->name);
            $keywords = array_merge($keywords, preg_split('/[-\s]+/', strtolower((string) $product->category->slug ?? '')) ?: []);
        }

        // From name and tags
        $keywords = array_merge($keywords, preg_split('/[-\s]+/', strtolower((string) $product->name)) ?: []);
        if (is_array($product->tags)) {
            $keywords = array_merge($keywords, array_map('strtolower', $product->tags));
        }

        // Heuristic product type extraction from name
        $name = strtolower((string) $product->name);
        $typeHints = ['tshirt' => ['tshirt','t-shirt','tee'], 'shirt' => ['shirt','top'], 'shoe' => ['shoe','sneaker','trainer'], 'dress' => ['dress'], 'pant' => ['pant','pants','trouser'], 'bag' => ['bag','backpack']];
        foreach ($typeHints as $base => $alts) {
            foreach ($alts as $needle) {
                if (str_contains($name, $needle)) {
                    $keywords[] = $base;
                    $keywords = array_merge($keywords, $alts);
                    break 2;
                }
            }
        }

        // Brand / material (if present)
        if (! empty($product->brand)) {
            $keywords[] = strtolower((string) $product->brand);
        }
        if (is_array($product->attributes ?? null)) {
            $material = $product->attributes['material'] ?? null;
            if ($material) {
                $keywords = array_merge($keywords, preg_split('/[-\s]+/', strtolower((string) $material)) ?: []);
            }
        }

        return array_values(array_filter(array_unique($keywords)));
    }
}
