<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TypesenseSyncSynonyms extends Command
{
    protected $signature = 'typesense:sync-synonyms {--dry : Show payload only}';

    protected $description = 'Build and push synonym sets to Typesense from categories and common variants';

    public function handle(): int
    {
        if (! config('typesense.enabled')) {
            $this->warn('Typesense is disabled (SEARCH_DRIVER != typesense).');
            return self::SUCCESS;
        }

        $synonyms = [];

        // Base apparel synonyms
        $synonyms[] = ['id' => 'tshirt', 'synonyms' => ['tshirt','t-shirt','tee','t shirt','tees','tshirts']];
        $synonyms[] = ['id' => 'sneaker', 'synonyms' => ['sneaker','sneakers','trainer','trainers','running shoes','sport shoes']];
        $synonyms[] = ['id' => 'pant', 'synonyms' => ['pant','pants','trouser','trousers']];
        $synonyms[] = ['id' => 'shoe', 'synonyms' => ['shoe','shoes','footwear']];
        $synonyms[] = ['id' => 'mens', 'synonyms' => ["men's", 'mens', 'men', 'man']];
        $synonyms[] = ['id' => 'womens', 'synonyms' => ["women's", 'womens', 'women', 'woman']];
        $synonyms[] = ['id' => 'kids', 'synonyms' => ['kids', 'kid', 'child', 'children', 'boys', 'girls']];

        // Category-derived synonyms (name + slug variants)
        Category::query()->where('is_active', true)->chunk(500, function ($cats) use (&$synonyms) {
            foreach ($cats as $cat) {
                $name = strtolower((string) $cat->name);
                $slug = strtolower((string) $cat->slug);
                $variants = array_values(array_unique(array_filter([
                    $name,
                    str_replace('-', ' ', $slug),
                    str_replace('-', '', $slug),
                ])));

                if (count($variants) >= 2) {
                    $synonyms[] = [
                        'id' => 'cat-' . $cat->id,
                        'synonyms' => $variants,
                    ];
                }
            }
        });

        if ($this->option('dry')) {
            $this->line(json_encode($synonyms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $config = config('typesense');
        $node = $config['nearest_node'] ?? $config['nodes'][0];
        $base = sprintf('%s://%s:%s', $node['protocol'], $node['host'], $node['port']);
        $collection = $config['collection'];

        $client = Http::withHeaders([
                'X-TYPESENSE-API-KEY' => $config['api_key'],
                'Content-Type' => 'application/json',
            ])
            ->baseUrl($base)
            ->timeout($config['connection_timeout_seconds'] ?? 2)
            ->acceptJson();

        foreach ($synonyms as $set) {
            $payload = ['synonyms' => $set['synonyms']];
            if (isset($set['root'])) {
                $payload['root'] = $set['root'];
            }

            $resp = $client->put("/collections/{$collection}/synonyms/{$set['id']}", $payload);
            if (! $resp->successful()) {
                $this->error('Failed to push synonym ' . $set['id'] . ': ' . $resp->body());
                return self::FAILURE;
            }
        }

        $this->info('Pushed ' . count($synonyms) . ' synonym sets to Typesense.');
        return self::SUCCESS;
    }
}
