<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CjTestSourcingLookup extends Command
{
    protected $signature = 'cj:test-sourcing-lookup
                            {--ids= : IDs/SKUs separated by spaces/commas/newlines}
                            {--file= : Path to a file containing one ID/SKU per line}
                            {--json : Dump raw per-id results as JSON}
                            {--limit=0 : Only test the first N ids (0 = all)}';

    protected $description = 'Debug CJ sourcing lookup: resolves sourcing IDs via /product/sourcing/query and CJSPU SKUs via /product/query, printing codes/messages per id.';

    public function handle(): int
    {
        $ids = $this->readIds();
        if ($ids === []) {
            $this->error('No ids provided. Use --ids=... or --file=/path/to/ids.txt');
            return self::INVALID;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $ids = array_slice($ids, 0, $limit);
        }

        [$sourcingIds, $productSkus] = $this->splitIds($ids);

        $this->info('CJ sourcing lookup test');
        $this->line('Total: ' . count($ids));
        $this->line('Sourcing IDs: ' . count($sourcingIds));
        $this->line('Product SKUs (CJSPU...): ' . count($productSkus));
        $this->line('');

        $client = app(CJDropshippingClient::class);

        $results = [];

        if ($sourcingIds !== []) {
            $results = array_merge($results, $this->testSourcingIds($client, $sourcingIds));
        }

        if ($productSkus !== []) {
            $results = array_merge($results, $this->testProductSkus($client, $productSkus));
        }

        $ok = array_values(array_filter($results, fn (array $r) => (bool) ($r['ok'] ?? false)));
        $fail = array_values(array_filter($results, fn (array $r) => ! (bool) ($r['ok'] ?? false)));

        $this->line('');
        $this->info('Summary');
        $this->line('OK: ' . count($ok));
        $this->line('Not found / failed: ' . count($fail));

        if ((bool) $this->option('json')) {
            $this->line(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $rows = array_map(function (array $r): array {
            return [
                $r['id'] ?? null,
                $r['type'] ?? null,
                $r['ok'] ? 'yes' : 'no',
                $r['cj_code'] ?? null,
                $r['cj_message'] ?? null,
                $r['cj_pid'] ?? null,
                $r['requestId'] ?? null,
            ];
        }, array_slice($results, 0, 50));

        $this->table(['id', 'type', 'ok', 'cj_code', 'cj_message', 'cj_pid', 'requestId'], $rows);

        if (count($results) > 50) {
            $this->line('Showing first 50 rows. Re-run with --json to see all.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function readIds(): array
    {
        $raw = (string) ($this->option('ids') ?? '');

        $file = (string) ($this->option('file') ?? '');
        if ($file !== '' && is_file($file)) {
            $raw = (string) file_get_contents($file);
        }

        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = [];

        foreach ($parts as $part) {
            $id = trim((string) $part);
            if ($id === '') {
                continue;
            }
            // Strip common invisible/control characters that come from copy/paste.
            $id = preg_replace('/[\p{C}]/u', '', $id) ?? $id;
            $id = trim($id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{0: array<int,string>, 1: array<int,string>}
     */
    private function splitIds(array $ids): array
    {
        $sourcingIds = [];
        $productSkus = [];

        foreach ($ids as $id) {
            $u = Str::upper($id);
            if (Str::startsWith($u, 'CJSPU')) {
                $productSkus[] = $id;
                continue;
            }
            $sourcingIds[] = $id;
        }

        return [
            array_values(array_unique($sourcingIds)),
            array_values(array_unique($productSkus)),
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function testSourcingIds(CJDropshippingClient $client, array $ids): array
    {
        $baseUrl = rtrim((string) config('services.cj.base_url'), '/');
        $token = $client->withToken();
        $platformToken = (string) config('services.cj.platform_token', '');

        $out = [];

        foreach (array_chunk($ids, 10) as $chunk) {
            $response = Http::withHeaders(array_filter([
                    'CJ-Access-Token' => $token,
                    'CJ-Platform-Token' => $platformToken !== '' ? $platformToken : null,
                    'Accept' => 'application/json',
                ]))
                ->timeout((int) config('services.cj.timeout', 10))
                ->acceptJson()
                ->post($baseUrl . '/v1/product/sourcing/query', [
                    'sourceIds' => array_values($chunk),
                    'pageNum' => 1,
                    'pageSize' => max(20, count($chunk)),
                ]);

            $payload = $response->json();
            if (! is_array($payload)) {
                foreach ($chunk as $id) {
                    $out[] = [
                        'id' => $id,
                        'type' => 'sourcingId',
                        'ok' => false,
                        'cj_code' => null,
                        'cj_message' => 'invalid_json',
                        'requestId' => null,
                    ];
                }
                continue;
            }

            $items = $payload['data'] ?? null;
            $list = [];
            if (is_array($items) && array_is_list($items)) {
                $list = $items;
            } elseif (is_array($items)) {
                $list = [$items];
            }

            $bySourceId = [];
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $sid = trim((string) ($row['sourceId'] ?? $row['cjSourcingId'] ?? $row['sourcingId'] ?? ''));
                if ($sid !== '') {
                    $bySourceId[$sid] = $row;
                }
            }

            foreach ($chunk as $id) {
                $row = $bySourceId[$id] ?? null;
                $out[] = [
                    'id' => $id,
                    'type' => 'sourcingId',
                    'ok' => (bool) (($payload['result'] ?? null) === true && (int) ($payload['code'] ?? 0) === 200 && is_array($row)),
                    'cj_code' => $payload['code'] ?? null,
                    'cj_message' => $payload['message'] ?? null,
                    'cj_pid' => is_array($row) ? ($row['cjProductId'] ?? null) : null,
                    'requestId' => $payload['requestId'] ?? null,
                    'raw_row' => $row,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function testProductSkus(CJDropshippingClient $client, array $skus): array
    {
        $baseUrl = rtrim((string) config('services.cj.base_url'), '/');
        $token = $client->withToken();
        $platformToken = (string) config('services.cj.platform_token', '');

        $out = [];

        foreach (array_chunk($skus, 8) as $group) {
            /** @var array<string, Response> $responses */
            $responses = Http::pool(function ($pool) use ($group, $baseUrl, $token, $platformToken) {
                $requests = [];
                foreach ($group as $sku) {
                    $requests[] = $pool
                        ->as($sku)
                        ->withHeaders(array_filter([
                            'CJ-Access-Token' => $token,
                            'CJ-Platform-Token' => $platformToken !== '' ? $platformToken : null,
                            'Accept' => 'application/json',
                        ]))
                        ->timeout((int) config('services.cj.timeout', 10))
                        ->acceptJson()
                        ->get($baseUrl . '/v1/product/query', [
                            'productSku' => $sku,
                            'features' => 'enable_inventory',
                            'countryCode' => env('CJ_DEFAULT_WAREHOUSE', 'CN'),
                        ]);
                }
                return $requests;
            });

            foreach ($group as $sku) {
                $resp = $responses[$sku] ?? null;
                $payload = $resp instanceof Response ? $resp->json() : null;

                if (! is_array($payload)) {
                    $out[] = [
                        'id' => $sku,
                        'type' => 'productSku',
                        'ok' => false,
                        'cj_code' => null,
                        'cj_message' => $resp instanceof Response ? ('http_' . $resp->status()) : 'no_response',
                        'requestId' => null,
                    ];
                    continue;
                }

                $data = $payload['data'] ?? null;
                $pid = is_array($data) ? trim((string) ($data['pid'] ?? $data['productId'] ?? $data['id'] ?? '')) : '';

                $out[] = [
                    'id' => $sku,
                    'type' => 'productSku',
                    'ok' => (bool) (($payload['result'] ?? null) === true && (int) ($payload['code'] ?? 0) === 200 && $pid !== ''),
                    'cj_code' => $payload['code'] ?? null,
                    'cj_message' => $payload['message'] ?? null,
                    'cj_pid' => $pid !== '' ? $pid : null,
                    'requestId' => $payload['requestId'] ?? null,
                ];
            }
        }

        return $out;
    }
}

