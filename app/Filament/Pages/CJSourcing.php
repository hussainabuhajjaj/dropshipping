<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductMediaService;
use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\Category;
use App\Services\Api\ApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use App\Filament\Pages\BasePage;
use UnitEnum;

class CJSourcing extends BasePage
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static UnitEnum|string|null $navigationGroup = 'Integrations';

    protected static ?int $navigationSort = 93;

    protected string $view = 'filament.pages.cj-sourcing';

    public ?string $productUrl = null;
    public ?string $note = null;
    public ?string $sourceId = null;
    public ?string $sourceIdsInput = null;
    public ?array $results = null;
    public array $sourceProducts = [];
    public array $invalidLookupIds = [];
    public array $notFoundLookupIds = [];
    public array $importedResolvedProductIds = [];
    public array $selectedResolvedProductIds = [];
    public bool $importEnrich = true;
    public bool $importAutoActivate = true;
    public bool $importSkipExisting = true;
    public bool $importQueueTranslations = true;
    public bool $importQueueSeo = true;
    public bool $importForceReprice = false;
    public ?int $importDefaultCategoryId = null;
    /** @var array<int, string> */
    public array $categoryOptions = [];
    public int $importBatchSize = 10;
    public ?string $previewPid = null;
    public ?array $previewProduct = null;
    public array $previewVariants = [];
    public array $previewInventory = [];
    public array $previewImages = [];
    public ?string $previewError = null;
    public array $importPreviewProducts = [];
    public array $importPreviewFailedPids = [];
    public ?string $importPreviewMode = null;
    public ?string $importPreviewError = null;
    public int $pageNum = 1;
    public int $pageSize = 20;
    public ?string $statusFilter = null;

    public function mount(): void
    {
        $this->loadCategoryOptions();
        $this->refreshList();
    }

    public function loadCategoryOptions(): void
    {
        // Avoid a full categories query inside the Blade render on every request.
        $this->categoryOptions = Cache::remember('admin:cj_sourcing:category_options_v1', now()->addMinutes(30), function (): array {
            return Category::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        });
    }

    public function createRequest(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $this->validate([
            'productUrl' => ['required', 'url'],
            'sourceId' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            app(CJDropshippingClient::class)->createSourcing($this->productUrl, $this->note, $this->sourceId);
            Notification::make()->title('Sourcing submitted')->success()->send();
            $this->productUrl = null;
            $this->note = null;
            $this->sourceId = null;
            $this->refreshList();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function refreshList(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        try {
            $cacheKey = 'admin:cj_sourcing:list_v1:' . $this->pageNum . ':' . $this->pageSize;
            $this->results = Cache::remember($cacheKey, now()->addSeconds(20), function () {
                $resp = app(CJDropshippingClient::class)->querySourcing(null, $this->pageNum, $this->pageSize);
                return $resp->data ?? null;
            });
        } catch (ApiException $e) {
            $this->results = null;
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            $this->results = null;
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function useSourceIdsForLookup(string $sourceIds): void
    {
        $this->sourceIdsInput = $sourceIds;
        $this->fetchSourceProducts();
    }

    public function normalizeSourceIdsInput(): void
    {
        $raw = (string) ($this->sourceIdsInput ?? '');
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $beforeCount = count($parts);
        $removedControlChars = 0;
        $normalized = [];

        foreach ($parts as $part) {
            $id = trim((string) $part);
            if ($id === '') {
                continue;
            }

            // Strip control chars (often copied from CJ/WhatsApp/Sheets).
            $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $id);
            if ($clean !== $id) {
                $removedControlChars++;
            }

            $clean = trim((string) $clean);
            if ($clean === '') {
                continue;
            }

            $normalized[] = $clean;
        }

        $unique = array_values(array_unique($normalized));
        $afterCount = count($unique);

        $this->sourceIdsInput = implode("\n", $unique);

        Notification::make()
            ->title('Source IDs normalized')
            ->body("Items: {$beforeCount} → {$afterCount}. Removed control chars: {$removedControlChars}.")
            ->success()
            ->send();
    }

    public function setStatusFilter(?string $status): void
    {
        $status = $status !== null ? trim($status) : null;
        $this->statusFilter = $status !== '' ? $status : null;
    }

    public function fetchSourceProducts(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        [$sourceIds, $invalidIds] = $this->resolveSourceIds();
        $this->invalidLookupIds = $invalidIds;
        $this->notFoundLookupIds = [];

        if ($invalidIds !== []) {
            $this->sourceProducts = [];
            Notification::make()
                ->title('Invalid lookup ids')
                ->body('Remove empty or malformed ids and try again.')
                ->danger()
                ->send();

            return;
        }

        if ($sourceIds === []) {
            $this->sourceProducts = [];
            Notification::make()
                ->title('No source ids')
                ->body('Paste one or more source ids, or refresh the sourcing list first.')
                ->warning()
                ->send();

            return;
        }

        try {
            Log::info('CJ sourcing bulk lookup request', [
                'source_ids' => $sourceIds,
                'source_ids_count' => count($sourceIds),
                'manual_input' => $this->sourceIdsInput,
            ]);

            [$resolvedProducts, $notFoundIds] = $this->querySourceProductsWithFallback($sourceIds);
            $this->sourceProducts = $resolvedProducts;
            $this->notFoundLookupIds = $notFoundIds;
            $this->syncImportedResolvedProductIds();
            $this->selectedResolvedProductIds = collect($this->sourceProducts)
                ->filter(fn ($item) => is_array($item))
                ->map(fn (array $item) => trim((string) ($item['cjProductId'] ?? '')))
                ->filter()
                ->reject(fn (string $pid) => in_array($pid, $this->importedResolvedProductIds, true))
                ->unique()
                ->values()
                ->all();

            if ($this->sourceProducts === []) {
                Notification::make()
                    ->title('No data returned')
                    ->body('CJ returned no sourcing records for the provided source ids.')
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title('Source products loaded')
                    ->body('Loaded ' . count($this->sourceProducts) . ' sourcing records from CJ.')
                    ->success()
                    ->send();
            }
        } catch (ApiException $e) {
            $this->sourceProducts = [];
            $this->importedResolvedProductIds = [];
            $this->selectedResolvedProductIds = [];
            Log::warning('CJ sourcing bulk lookup API exception', [
                'source_ids' => $sourceIds,
                'message' => $e->getMessage(),
                'status' => $e->status,
                'code' => $e->codeString,
                'body' => $e->body,
                'request_id' => $e->requestId,
            ]);
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            $this->sourceProducts = [];
            $this->importedResolvedProductIds = [];
            $this->selectedResolvedProductIds = [];
            Log::error('CJ sourcing bulk lookup unexpected exception', [
                'source_ids' => $sourceIds,
                'message' => $e->getMessage(),
            ]);
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    protected function resolveSourceIds(): array
    {
        $manualIds = collect(preg_split('/[\s,]+/', (string) $this->sourceIdsInput, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(static fn (string $id) => trim($id))
            ->filter()
            ->values()
            ->all();

        if ($manualIds !== []) {
            return $this->partitionLookupIds($manualIds);
        }

        $list = $this->results['list'] ?? $this->results['data'] ?? $this->results;
        if (! is_array($list)) {
            return [[], []];
        }

        $ids = collect($list)
            ->map(static fn ($item) => is_array($item)
                ? ($item['cjSourcingId'] ?? $item['sourcingId'] ?? $item['sourceId'] ?? null)
                : null)
            ->filter(static fn ($id) => is_scalar($id) && trim((string) $id) !== '')
            ->map(static fn ($id) => trim((string) $id))
            ->unique()
            ->values()
            ->all();

        return [$ids, []];
    }

    protected function partitionLookupIds(array $ids): array
    {
        $valid = [];
        $invalid = [];

        foreach ($ids as $id) {
            $normalized = trim((string) $id);

            if ($normalized === '') {
                continue;
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
                $invalid[] = $normalized;
                continue;
            }

            $valid[] = $normalized;
        }

        return [
            array_values(array_unique($valid)),
            array_values(array_unique($invalid)),
        ];
    }

    public function importResolvedProduct(string $pid): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $pid = trim($pid);
        if ($pid === '') {
            Notification::make()
                ->title('Invalid CJ product')
                ->body('No CJ product id was provided for import.')
                ->warning()
                ->send();

            return;
        }

        try {
            $existing = Product::query()->where('cj_pid', $pid)->first();

            if ($existing && $this->importSkipExisting) {
                $this->syncImportedResolvedProductIds();

                Notification::make()
                    ->title('Already imported')
                    ->body("Product already exists as {$existing->name}.")
                    ->success()
                    ->send();

                return;
            }

            $result = app(CjProductImportService::class)->importBulkWithPipeline([
                'pids' => [$pid],
                'enrich' => $this->importEnrich,
                'force_activate' => $this->importAutoActivate,
                'force_reprice' => $this->importForceReprice,
            ]);

            if (($result['activated'] ?? 0) > 0 || ($result['imported'] ?? 0) > 0) {
                $this->syncImportedResolvedProductIds();

                Notification::make()
                    ->title('Product imported')
                    ->body(
                        ($result['activated'] ?? 0) > 0
                            ? 'Product imported and activated with weight-based pricing.'
                            : 'Product imported with weight-based pricing.'
                    )
                    ->success()
                    ->send();

                return;
            }

            if ((int) ($result['removed'] ?? 0) > 0) {
                Notification::make()
                    ->title('Product unavailable on CJ')
                    ->body("CJ product {$pid} is removed, unavailable, or no longer importable from CJ.")
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Import failed')
                ->body("CJ product {$pid} could not be imported by the pipeline.")
                ->warning()
                ->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ API error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Import error')->body($e->getMessage())->danger()->send();
        }
    }

    public function toggleResolvedProductSelection(string $pid): void
    {
        $pid = trim($pid);

        if ($pid === '') {
            return;
        }

        if (in_array($pid, $this->selectedResolvedProductIds, true)) {
            $this->selectedResolvedProductIds = array_values(array_filter(
                $this->selectedResolvedProductIds,
                static fn (string $selectedPid) => $selectedPid !== $pid
            ));

            return;
        }

        $this->selectedResolvedProductIds[] = $pid;
        $this->selectedResolvedProductIds = array_values(array_unique($this->selectedResolvedProductIds));
    }

    public function selectAllResolvedProducts(): void
    {
        $this->selectedResolvedProductIds = collect($this->sourceProducts)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => trim((string) ($item['cjProductId'] ?? '')))
            ->filter()
            ->reject(fn (string $pid) => in_array($pid, $this->importedResolvedProductIds, true))
            ->unique()
            ->values()
            ->all();
    }

    public function clearResolvedProductSelection(): void
    {
        $this->selectedResolvedProductIds = [];
    }

    public function previewSelectedResolvedProducts(): void
    {
        $this->openImportPreview($this->selectedResolvedProductIds, 'bulk');
    }

    public function importSelectedResolvedProducts(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $pids = collect($this->selectedResolvedProductIds)
            ->map(fn ($pid) => trim((string) $pid))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($this->importSkipExisting) {
            $pids = array_values(array_filter(
                $pids,
                fn (string $pid) => ! in_array($pid, $this->importedResolvedProductIds, true)
            ));
        }

        if ($pids === []) {
            Notification::make()
                ->title('No products selected')
                ->body('Select one or more resolved CJ products to import.')
                ->warning()
                ->send();

            return;
        }

        try {
            $result = app(CjProductImportService::class)->importBulkWithPipeline([
                'pids' => $pids,
                'enrich' => $this->importEnrich,
                'force_activate' => $this->importAutoActivate,
                'skip_translations' => ! $this->importQueueTranslations,
                'skip_seo' => ! $this->importQueueSeo,
                'default_category_id' => $this->importDefaultCategoryId,
                'batch_size' => $this->importBatchSize,
                'force_reprice' => $this->importForceReprice,
            ]);

            $imported = (int) ($result['imported'] ?? 0);
            $activated = (int) ($result['activated'] ?? 0);

            $this->syncImportedResolvedProductIds();
            $this->selectedResolvedProductIds = array_values(array_diff(
                $this->selectedResolvedProductIds,
                $this->importedResolvedProductIds
            ));

            if ($imported > 0 || $activated > 0) {
                Notification::make()
                    ->title('Bulk import completed')
                    ->body("Imported {$imported} product(s); activated {$activated}.")
                    ->success()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Bulk import failed')
                ->body('No selected CJ products were imported.')
                ->warning()
                ->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ API error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Import error')->body($e->getMessage())->danger()->send();
        }
    }

    public function previewResolvedProduct(string $pid): void
    {
        $pid = trim($pid);
        $this->previewPid = $pid !== '' ? $pid : null;
        $this->previewProduct = null;
        $this->previewVariants = [];
        $this->previewInventory = [];
        $this->previewImages = [];
        $this->previewError = null;

        if ($pid === '') {
            return;
        }

        try {
            $client = app(CJDropshippingClient::class);
            $productResp = $client->getProduct($pid);
            $productData = $productResp->data ?? null;

            if (! is_array($productData) || $productData === []) {
                $this->previewError = 'CJ returned no product details for this product id.';
                return;
            }

            $variantResp = $client->getVariantsByPid($pid);
            $variants = is_array($variantResp->data ?? null) ? $variantResp->data : [];

            $stockResp = $client->getStockByPid($pid);
            $stockRows = is_array($stockResp->data ?? null) ? $stockResp->data : [];

            $inventoryByVid = collect($stockRows)
                ->filter(fn ($row) => is_array($row))
                ->mapWithKeys(function (array $row): array {
                    $vid = trim((string) ($row['vid'] ?? ''));
                    if ($vid === '') {
                        return [];
                    }

                    return [$vid => [
                        'countryCode' => $row['countryCode'] ?? null,
                        'warehouse' => $row['areaEn'] ?? null,
                        'totalInventoryNum' => (int) ($row['totalInventoryNum'] ?? $row['storageNum'] ?? 0),
                        'cjInventoryNum' => (int) ($row['cjInventoryNum'] ?? 0),
                        'factoryInventoryNum' => (int) ($row['factoryInventoryNum'] ?? 0),
                    ]];
                })
                ->all();

            $variants = collect($variants)->map(function (array $variant) use ($inventoryByVid): array {
                $vid = trim((string) ($variant['vid'] ?? ''));
                $inventory = $inventoryByVid[$vid] ?? null;

                return [
                    'vid' => $vid,
                    'variantSku' => $variant['variantSku'] ?? null,
                    'variantNameEn' => $variant['variantNameEn'] ?? null,
                    'variantKey' => $variant['variantKey'] ?? null,
                    'variantSellPrice' => $variant['variantSellPrice'] ?? null,
                    'variantSugSellPrice' => $variant['variantSugSellPrice'] ?? null,
                    'variantWeight' => $variant['variantWeight'] ?? null,
                    'inventory' => $inventory,
                ];
            })->values()->all();

            $mediaService = app(CjProductMediaService::class);
            $this->previewImages = $mediaService->extractImageUrls($productData, $variants);
            $this->previewProduct = $productData;
            $this->previewVariants = $variants;
            $this->previewInventory = $stockRows;
        } catch (ApiException $e) {
            $this->previewError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->previewError = $e->getMessage();
        }
    }

    public function openImportPreview(array $pids, string $mode = 'single'): void
    {
        $pids = array_values(array_unique(array_filter(
            array_map(static fn ($pid) => trim((string) $pid), $pids),
            static fn (string $pid) => $pid !== ''
        )));

        if ($pids === []) {
            Notification::make()
                ->title('No CJ products selected')
                ->body('Select one or more resolved CJ products first.')
                ->warning()
                ->send();

            return;
        }

        $this->importPreviewProducts = [];
        $this->importPreviewFailedPids = [];
        $this->importPreviewMode = $mode;
        $this->importPreviewError = null;

        $service = app(CjProductImportService::class);

        foreach ($pids as $pid) {
            try {
                $preview = $service->buildImportPreview($pid);
                $this->importPreviewProducts[] = $preview;
            } catch (ApiException $e) {
                $this->importPreviewFailedPids[] = $pid;
                Log::warning('CJ sourcing preview failed', ['pid' => $pid, 'error' => $e->getMessage()]);
            } catch (\Throwable $e) {
                $this->importPreviewFailedPids[] = $pid;
                Log::warning('CJ sourcing preview failed unexpectedly', ['pid' => $pid, 'error' => $e->getMessage()]);
            }
        }

        if ($this->importPreviewProducts === []) {
            $this->importPreviewError = 'CJ preview could not be loaded for the selected product(s).';
            Notification::make()->title('Preview unavailable')->body($this->importPreviewError)->danger()->send();
            return;
        }

        $firstPreview = $this->importPreviewProducts[0];
        $this->previewPid = $firstPreview['pid'] ?? null;
        $this->previewProduct = is_array($firstPreview['product'] ?? null) ? $firstPreview['product'] : null;
        $this->previewVariants = is_array($firstPreview['variants'] ?? null) ? $firstPreview['variants'] : [];
        $this->previewInventory = is_array($firstPreview['inventory'] ?? null) ? $firstPreview['inventory'] : [];
        $this->previewImages = is_array($firstPreview['images'] ?? null) ? $firstPreview['images'] : [];
        $this->previewError = null;

        if ($this->importPreviewFailedPids !== []) {
            $this->importPreviewError = 'Some selected CJ products could not be previewed and cannot be imported yet.';
        }

        $this->dispatch('open-modal', id: $this->getImportPreviewModalId());
    }

    public function closeImportPreview(): void
    {
        $this->importPreviewProducts = [];
        $this->importPreviewFailedPids = [];
        $this->importPreviewMode = null;
        $this->importPreviewError = null;
        $this->previewPid = null;
        $this->previewProduct = null;
        $this->previewVariants = [];
        $this->previewInventory = [];
        $this->previewImages = [];
        $this->previewError = null;
    }

    public function confirmImportPreview(): void
    {
        if ($this->importPreviewProducts === []) {
            Notification::make()->title('No preview data')->warning()->send();
            return;
        }

        $hasInvalidPreview = collect($this->importPreviewProducts)
            ->contains(fn (array $preview): bool => ! (bool) ($preview['validation']['is_valid'] ?? false));

        if ($hasInvalidPreview || $this->importPreviewFailedPids !== []) {
            Notification::make()
                ->title('Preview validation failed')
                ->body('Resolve the preview errors before importing.')
                ->warning()
                ->send();
            return;
        }

        $pids = collect($this->importPreviewProducts)
            ->map(fn (array $preview) => trim((string) ($preview['pid'] ?? '')))
            ->filter()
            ->values()
            ->all();

        $this->dispatch('close-modal', id: $this->getImportPreviewModalId());

        if (($this->importPreviewMode ?? 'single') === 'bulk') {
            $this->selectedResolvedProductIds = $pids;
            $this->importSelectedResolvedProducts();
            return;
        }

        $this->importResolvedProduct($pids[0] ?? '');
    }

    public function getImportPreviewModalId(): string
    {
        return $this->getId() . '-import-preview';
    }

    protected function querySourceProductsWithFallback(array $sourceIds): array
    {
        $client = app(CJDropshippingClient::class);
        $cacheKey = 'admin:cj_sourcing:lookup_v1:' . md5(implode('|', $sourceIds));

        return Cache::remember($cacheKey, now()->addSeconds(45), function () use ($sourceIds, $client): array {
            $resolved = [];
            $notFound = [];

            foreach (array_chunk($sourceIds, 10) as $chunk) {
                try {
                    $resp = $client->querySourcingBySourceIds($chunk);

                    Log::info('CJ sourcing bulk lookup response', [
                        'source_ids' => $chunk,
                        'ok' => $resp->ok,
                        'status' => $resp->status,
                        'message' => $resp->message,
                        'data' => $resp->data,
                        'raw' => $resp->raw,
                    ]);

                    $items = $this->normalizeSourceProductsResponse($resp->data);

                    if ($items === []) {
                        $notFound = array_merge($notFound, $chunk);
                        continue;
                    }

                    $resolved = array_merge($resolved, $items);
                } catch (ApiException $e) {
                    Log::warning('CJ sourcing chunk lookup failed, retrying per id', [
                        'source_ids' => $chunk,
                        'message' => $e->getMessage(),
                        'status' => $e->status,
                        'code' => $e->codeString,
                        'body' => $e->body,
                        'request_id' => $e->requestId,
                    ]);

                    [$singleResolved, $singleNotFound] = $this->querySingleSourceIdsConcurrently($chunk, $client);
                    $resolved = array_merge($resolved, $singleResolved);
                    $notFound = array_merge($notFound, $singleNotFound);
                }
            }

            $resolvedBySourceId = collect($resolved)
                ->filter(fn ($item) => is_array($item))
                ->keyBy(fn (array $item) => (string) ($item['sourceId'] ?? spl_object_id((object) $item)))
                ->values()
                ->all();

            return [$resolvedBySourceId, array_values(array_unique($notFound))];
        });
    }

    protected function normalizeSourceProductsResponse(mixed $data): array
    {
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            return array_is_list($data['data']) ? $data['data'] : [$data['data']];
        }

        if (is_array($data)) {
            return [$data];
        }

        return [];
    }

    protected function syncImportedResolvedProductIds(): void
    {
        $pids = collect($this->sourceProducts)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $item['cjProductId'] ?? null)
            ->filter(fn ($pid) => is_scalar($pid) && trim((string) $pid) !== '')
            ->map(fn ($pid) => trim((string) $pid))
            ->unique()
            ->values()
            ->all();

        if ($pids === []) {
            $this->importedResolvedProductIds = [];
            return;
        }

        $this->importedResolvedProductIds = Product::query()
            ->whereIn('cj_pid', $pids)
            ->pluck('cj_pid')
            ->map(fn ($pid) => trim((string) $pid))
            ->values()
            ->all();
    }

    protected function querySingleSourceIdsConcurrently(array $ids, CJDropshippingClient $client): array
    {
        $resolved = [];
        $notFound = [];
        $baseUrl = rtrim((string) config('services.cj.base_url'), '/');
        $token = $client->withToken();
        $platformToken = (string) config('services.cj.platform_token', '');

        foreach (array_chunk($ids, 8) as $group) {
            $responses = Http::pool(function ($pool) use ($group, $baseUrl, $token, $platformToken) {
                $requests = [];

                foreach ($group as $id) {
                    $request = $pool
                        ->as($id)
                        ->withHeaders(array_filter([
                            'CJ-Access-Token' => $token,
                            'CJ-Platform-Token' => $platformToken !== '' ? $platformToken : null,
                            'Accept' => 'application/json',
                        ]))
                        ->timeout((int) config('services.cj.timeout', 10))
                        ->acceptJson()
                        ->post($baseUrl . '/v1/product/sourcing/query', [
                            'sourceIds' => [$id],
                            'pageNum' => 1,
                            'pageSize' => 20,
                        ]);

                    $requests[] = $request;
                }

                return $requests;
            });

            foreach ($group as $id) {
                $response = $responses[$id] ?? null;

                if (! $response instanceof Response) {
                    $notFound[] = $id;
                    continue;
                }

                if (! $response->successful()) {
                    Log::warning('CJ sourcing single lookup failed', [
                        'source_id' => $id,
                        'http_status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);
                    $notFound[] = $id;
                    continue;
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    $notFound[] = $id;
                    continue;
                }

                if (($payload['result'] ?? null) !== true || (int) ($payload['code'] ?? 0) !== 200) {
                    Log::warning('CJ sourcing single lookup failed', [
                        'source_id' => $id,
                        'message' => $payload['message'] ?? 'Unknown error',
                        'code' => (string) ($payload['code'] ?? ''),
                        'body' => $payload,
                        'request_id' => $payload['requestId'] ?? null,
                    ]);
                    $notFound[] = $id;
                    continue;
                }

                $items = $this->normalizeSourceProductsResponse($payload['data'] ?? null);

                if ($items === []) {
                    $notFound[] = $id;
                    continue;
                }

                $resolved = array_merge($resolved, $items);
            }
        }

        return [$resolved, $notFound];
    }

}

