<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\ImportCjProductChunkJob;
use App\Models\Product;
use App\Services\CjPidClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CjSyncProductsV2 extends Command
{
    protected $signature = 'cj:sync-products-v2
        {--keyword= : sku/spu/product name keyword (CJ My Products)}
        {--categoryId= : CJ category id (CJ My Products)}
        {--startAt= : Filter start time (CJ My Products)}
        {--endAt= : Filter end time (CJ My Products)}
        {--isListed= : Filter by listing status (CJ My Products)}
        {--visiable= : Filter by visibility (CJ My Products)}
        {--hasPacked= : Filter by packed status (CJ My Products)}
        {--hasVirPacked= : Filter by virtual packed status (CJ My Products)}
        {--chunk=25 : Chunk size for inline/queued import}
        {--inline : Import immediately in the command (no queue)}
        {--no-claim : Do not claim PIDs before processing (debug only)}
        {--force : Process even if product already exists}
        {--sleep=0 : Sleep milliseconds between page fetches (rate-limit safety)}';

    protected $description = 'Sync ALL products from CJ "My Products" (myProduct/query) across all pages and import them.';

    public function handle(): int
    {
        $this->info('🔄 Starting CJ My Products Sync (ALL pages)...');

        // Global lock
        try {
            $lock = Cache::store('redis')->lock('cj:sync', 60 * 10);
        } catch (\Throwable $e) {
            $lock = Cache::lock('cj:sync', 60 * 10);
        }

        try {
            if (! $lock->block(30)) {
                $this->error('Another CJ sync is currently running. Exiting.');
                return self::FAILURE;
            }
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            $this->error('Timed out waiting to acquire the CJ sync lock.');
            $this->line('Possible causes: another sync running or stale lock.');
            return self::FAILURE;
        }

        try {
            $client = new CJDropshippingClient();

            /** @var CjProductImportService $importService */
            $importService = new CjProductImportService(
                $client,
                app('App\Domain\Products\Services\CjProductMediaService')
            );

            /** @var CjPidClaimService $claimService */
            $claimService = app(CjPidClaimService::class);

            $keyword = $this->option('keyword');
            $categoryId = $this->option('categoryId');
            $startAt = $this->option('startAt');
            $endAt = $this->option('endAt');
            $isListed = $this->option('isListed');
            $visiable = $this->option('visiable');
            $hasPacked = $this->option('hasPacked');
            $hasVirPacked = $this->option('hasVirPacked');

            $chunkSize = max(1, (int) $this->option('chunk'));
            $inline = (bool) $this->option('inline');
            $noClaim = (bool) $this->option('no-claim');
            $force = (bool) $this->option('force');
            $sleepMs = max(0, (int) $this->option('sleep'));

            // Request max size (CJ usually max 100)
            $requestedSize = 100;

            $page = 1;
            $totalPages = null;

            // Metrics
            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;
            $totalProcessed = 0;

            $this->line('📡 Fetching CJ My Products pages...');
            $progress = $this->output->createProgressBar();
            $progress->setFormat('debug');
            $progress->start();

            while (true) {
                $resp = $this->fetchMyProductsPage($client, [
                    'page' => $page,
                    'size' => $requestedSize,
                    'keyword' => $keyword,
                    'categoryId' => $categoryId,
                    'startAt' => $startAt,
                    'endAt' => $endAt,
                    'isListed' => $isListed,
                    'visiable' => $visiable,
                    'hasPacked' => $hasPacked,
                    'hasVirPacked' => $hasVirPacked,
                ]);

                $data = $resp->data ?? [];
                $content = $data['content'] ?? [];

                if ($totalPages === null) {
                    $totalPages = (int) ($data['totalPages'] ?? 0);
                    $pageSize = (int) ($data['pageSize'] ?? $requestedSize);
                    $totalRecords = (int) ($data['totalRecords'] ?? 0);

                    $this->newLine();
                    $this->info("CJ paging detected: totalPages={$totalPages}, pageSize={$pageSize}, totalRecords={$totalRecords}");
                    if ($keyword) $this->line("Filter keyword={$keyword}");
                    if ($categoryId) $this->line("Filter categoryId={$categoryId}");
                    $this->newLine();
                }

                if (empty($content)) {
                    $this->newLine();
                    $this->info("✅ No content on page {$page}. Stopping.");
                    break;
                }

                $this->importMyProductsContent(
                    $content,
                    $chunkSize,
                    $inline,
                    $noClaim,
                    $force,
                    $importService,
                    $claimService,
                    $progress,
                    $imported,
                    $updated,
                    $skipped,
                    $errors,
                    $totalProcessed
                );

                // Stop conditions
                if ($totalPages !== null && $totalPages > 0 && $page >= $totalPages) {
                    break;
                }
                if ($totalPages !== null && $totalPages <= 0) {
                    break;
                }

                $page++;

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }

            $progress->finish();

            $this->newLine(2);
            $this->info('🎉 CJ My Products Sync Complete!');
            $this->table(['Metric', 'Count'], [
                ['Imported', $imported],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total Processed', $totalProcessed],
            ]);

            return $errors > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Fatal Error: ' . $e->getMessage());
            Log::error('CJ My Products Sync Fatal Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    private function fetchMyProductsPage(CJDropshippingClient $client, array $params): object
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $size = min(100, max(1, (int) ($params['size'] ?? 100)));

        $filters = array_filter([
            'pageNum' => $page,
            'pageSize' => $size,
            'keyword' => $params['keyword'] ?? null,
            'categoryId' => $params['categoryId'] ?? null,
            'startAt' => $params['startAt'] ?? null,
            'endAt' => $params['endAt'] ?? null,
            'isListed' => $params['isListed'] ?? null,
            'visiable' => $params['visiable'] ?? null,
            'hasPacked' => $params['hasPacked'] ?? null,
            'hasVirPacked' => $params['hasVirPacked'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $res = $client->listMyProducts($filters);

        $payload = $res->data ?? null;
        if (is_object($payload)) {
            $payload = json_decode(json_encode($payload), true);
        }

        // unwrap if ApiResponse gives full body: {code, success, data:{...}}
        if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        if (! is_array($payload)) {
            return (object) [
                'data' => [
                    'content' => [],
                    'pageNumber' => $page,
                    'pageSize' => $size,
                    'totalPages' => 0,
                    'totalRecords' => 0,
                ],
                '_raw' => $res,
            ];
        }

        $content = $payload['content'] ?? [];

        return (object) [
            'data' => [
                'content' => is_array($content) ? $content : [],
                'pageNumber' => (int) ($payload['pageNumber'] ?? $page),
                'pageSize' => (int) ($payload['pageSize'] ?? $size),
                'totalPages' => (int) ($payload['totalPages'] ?? 0),
                'totalRecords' => (int) ($payload['totalRecords'] ?? 0),
            ],
            '_raw' => $res,
        ];
    }

    private function importMyProductsContent(
        array $content,
        int $chunkSize,
        bool $inline,
        bool $noClaim,
        bool $force,
        CjProductImportService $importService,
        CjPidClaimService $claimService,
        $progressBar,
        int &$imported,
        int &$updated,
        int &$skipped,
        int &$errors,
        int &$totalProcessed
    ): void {
        $buffer = [];

        foreach ($content as $row) {
            if (! is_array($row)) {
                continue;
            }

            $totalProcessed++;

            // CJ My Products unique id
            $productId = (string) ($row['productId'] ?? '');
            if ($productId === '') {
                $errors++;
                continue;
            }

            // DB de-dupe
            if (! $force) {
                try {
                    if (Product::where('cj_pid', $productId)->exists()) {
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }
                } catch (\Throwable $e) {
                    Log::warning('CJ my-products existence check failed', [
                        'pid' => $productId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // PID claim
            if ($noClaim) {
                $token = bin2hex(random_bytes(16));
            } else {
                $token = $claimService->claim($productId, 60 * 60);
                if ($token === null) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }
            }

            // Attach claim token and pid; importer will normalize payload shapes.
            $row['pid'] = $row['pid'] ?? $productId;
            $row['id'] = $row['id'] ?? $productId;
            $row['_cj_claim_token'] = $token;

            $buffer[] = $row;

            if (count($buffer) >= $chunkSize) {
                $this->flushImportBuffer($buffer, $inline, $importService, $progressBar, $imported, $updated, $errors);
                $buffer = [];
            }
        }

        if (! empty($buffer)) {
            $this->flushImportBuffer($buffer, $inline, $importService, $progressBar, $imported, $updated, $errors);
        }
    }

    private function flushImportBuffer(
        array $buffer,
        bool $inline,
        CjProductImportService $importService,
        $progressBar,
        int &$imported,
        int &$updated,
        int &$errors
    ): void {
        if (empty($buffer)) {
            return;
        }

        if ($inline) {
            Log::info('CJ my-products: importing chunk inline', ['count' => count($buffer)]);
            try {
                $res = $importService->importBulkFromPayloads($buffer, [
                    'dispatchChunkSize' => 50,
                    'translate' => true,
                    'generateSeo' => true,
                    'syncMedia' => true,
                    'syncVariants' => true,
                ]);

                $imported += (int) ($res['created'] ?? 0);
                $updated += (int) ($res['updated'] ?? 0);
            } catch (\Throwable $e) {
                $errors += count($buffer);
                Log::error('CJ my-products: inline chunk import failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('CJ my-products: dispatching chunk to import queue', ['count' => count($buffer)]);
            ImportCjProductChunkJob::dispatch($buffer)->onQueue('import');
        }

        foreach ($buffer as $_) {
            $progressBar->advance();
        }
    }
}
