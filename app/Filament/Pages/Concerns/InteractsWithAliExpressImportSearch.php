<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Products\Services\AliExpressProductImportService;
use App\Models\AliExpressToken;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait InteractsWithAliExpressImportSearch
{
    public function searchProducts(): void
    {
        try {
            if (! $this->ensureAliExpressToken()) {
                return;
            }

            $state = $this->getFormState();
            $this->syncPreviewSettingsFromState($state);
            $this->applyFiltersAndReload($this->buildFiltersFromState($state));
            $this->notify('success', 'Preview Loaded ✓', 'Found ' . count($this->searchResults) . ' products.');
        } catch (\Exception $e) {
            Log::error('AliExpress preview failed', ['error' => $e->getMessage()]);
            $this->notify('danger', 'Preview Failed ✗', $e->getMessage());
        }
    }

    public function loadMoreResults(): void
    {
        if (! $this->ensureAliExpressToken()) {
            return;
        }

        if ($this->previewExhausted) {
            $this->notify('info', 'No more results', 'Reached the end of the dataset.');
            return;
        }

        $added = $this->fetchNextApiPage();
        $this->notify('success', 'More results loaded', "Added {$added} items.");
    }

    protected function resetPreviewState(): void
    {
        $this->searchResults = [];
        $this->previewed = false;
        $this->loadedApiPages = [];
        $this->previewExhausted = false;
        $this->nextApiPageToFetch = 1;
        $this->apiTotalCount = null;
    }

    protected function resolveTablePerPage(): int
    {
        $state = $this->getFormState();
        $raw = $state['page_size'] ?? $this->page_size ?? self::DEFAULT_PAGE_SIZE;

        return max(1, (int) $raw);
    }

    protected function getFormState(): array
    {
        $state = $this->form?->getState();

        return is_array($state) ? $state : [];
    }

    protected function syncPreviewSettingsFromState(array $state): void
    {
        $this->page_size = isset($state['page_size']) ? max(1, (int) $state['page_size']) : self::DEFAULT_PAGE_SIZE;
        $this->apiPageSize = min(self::API_PAGE_LIMIT, max(20, $this->page_size));
    }

    protected function syncActiveFilters(array $filters): void
    {
        $this->activeFilters = $filters;
        $this->activeFiltersHash = md5(json_encode($filters));
    }

    protected function syncActiveFiltersFromFormState(?array $state = null): void
    {
        $state ??= $this->getFormState();
        $this->syncPreviewSettingsFromState($state);
        $this->syncActiveFilters($this->buildFiltersFromState($state));
    }

    protected function syncActiveFiltersFromProperties(): void
    {
        $this->syncActiveFilters($this->buildFiltersFromProperties());
    }

    protected function buildFiltersFromState(array $state): array
    {
        $keyword = isset($state['keyword']) ? trim((string) $state['keyword']) : '';
        $minRating = isset($state['min_rating']) ? (int) $state['min_rating'] : 0;

        return [
            'categoryId' => isset($state['ali_category_id']) ? (int) $state['ali_category_id'] : null,
            'keyWord' => $keyword !== '' ? $keyword : null,
            'min' => isset($state['min_price']) ? (string) $state['min_price'] : null,
            'max' => isset($state['max_price']) ? (string) $state['max_price'] : null,
            'minRating' => $minRating > 0 ? $minRating : null,
            'inStockOnly' => ! empty($state['in_stock_only']) ? true : null,
            'ship_to_country' => $this->normalizeAliExpressScalar($state['ship_to_country'] ?? null),
            'target_currency' => $this->normalizeAliExpressScalar($state['target_currency'] ?? null),
            'target_language' => $this->normalizeAliExpressScalar($state['target_language'] ?? null),
            'remove_personal_benefit' => ! empty($state['remove_personal_benefit']) ? true : null,
            'deliverable_only' => ! empty($state['deliverable_only']) ? true : null,
            'biz_model' => $this->normalizeAliExpressScalar($state['biz_model'] ?? null),
            'province_code' => $this->normalizeAliExpressScalar($state['province_code'] ?? null),
            'city_code' => $this->normalizeAliExpressScalar($state['city_code'] ?? null),
        ];
    }

    protected function buildFiltersFromProperties(): array
    {
        $keyword = isset($this->keyword) ? trim((string) $this->keyword) : '';
        $minRating = isset($this->min_rating) ? (int) $this->min_rating : 0;

        return [
            'categoryId' => $this->ali_category_id ? (int) $this->ali_category_id : null,
            'keyWord' => $keyword !== '' ? $keyword : null,
            'min' => isset($this->min_price) ? (string) $this->min_price : null,
            'max' => isset($this->max_price) ? (string) $this->max_price : null,
            'minRating' => $minRating > 0 ? $minRating : null,
            'inStockOnly' => $this->in_stock_only ? true : null,
            'ship_to_country' => $this->normalizeAliExpressScalar($this->ship_to_country),
            'target_currency' => $this->normalizeAliExpressScalar($this->target_currency),
            'target_language' => $this->normalizeAliExpressScalar($this->target_language),
            'remove_personal_benefit' => $this->remove_personal_benefit ? true : null,
            'deliverable_only' => $this->deliverable_only ? true : null,
            'biz_model' => $this->normalizeAliExpressScalar($this->biz_model),
            'province_code' => $this->normalizeAliExpressScalar($this->province_code),
            'city_code' => $this->normalizeAliExpressScalar($this->city_code),
        ];
    }

    protected function applyFiltersAndReload(array $filters, bool $force = false): void
    {
        $hash = md5(json_encode($filters));
        if (! $force && $hash === $this->activeFiltersHash) {
            return;
        }

        $this->syncActiveFilters($filters);
        $this->apiPageSize = min(self::API_PAGE_LIMIT, max(20, (int) ($this->page_size ?? 20)));
        $this->resetPreviewState();
        $this->selectedProductIds = [];
        $this->fetchNextApiPage();
        $this->ensureLoadedForUiPage(1);
    }

    protected function refreshPreviewFromForm(): void
    {
        $this->syncActiveFiltersFromFormState();
    }

    public function updatedAliCategoryId(): void
    {
        $this->syncActiveFiltersFromProperties();
    }

    public function updatedKeyword(): void
    {
        $this->syncActiveFiltersFromProperties();
    }

    public function updatedMinPrice(): void
    {
        $this->syncActiveFiltersFromProperties();
    }

    public function updatedMaxPrice(): void
    {
        $this->syncActiveFiltersFromProperties();
    }

    public function updatedInStockOnly(): void
    {
        $this->syncActiveFiltersFromProperties();
    }

    protected function buildFilterPayload(): array
    {
        return array_filter([
            'categoryId' => $this->activeFilters['categoryId'] ?? null,
            'keyWord' => $this->activeFilters['keyWord'] ?? null,
            'min' => $this->activeFilters['min'] ?? null,
            'max' => $this->activeFilters['max'] ?? null,
            'minRating' => $this->activeFilters['minRating'] ?? null,
            'inStockOnly' => $this->activeFilters['inStockOnly'] ?? null,
            'local' => $this->activeFilters['target_language'] ?? self::DEFAULT_TARGET_LANGUAGE,
            'countryCode' => $this->activeFilters['ship_to_country'] ?? self::DEFAULT_SHIP_TO_COUNTRY,
            'currency' => $this->activeFilters['target_currency'] ?? self::DEFAULT_TARGET_CURRENCY,
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function fetchNextApiPage(): int
    {
        return $this->fetchApiPage($this->nextApiPageToFetch);
    }

    protected function fetchApiPage(int $page): int
    {
        if ($page < 1 || in_array($page, $this->loadedApiPages, true) || $this->previewExhausted) {
            return 0;
        }

        if (! $this->ensureAliExpressToken()) {
            return 0;
        }

        $payload = $this->buildFilterPayload();
        $response = null;
        $rawItems = [];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = app(AliExpressProductImportService::class)->searchPage(
                $payload,
                $page,
                $this->apiPageSize
            );
            $rawItems = $response['items'] ?? [];
            if (! empty($rawItems)) {
                break;
            }
        }

        if (empty($rawItems)) {
            $this->loadedApiPages[] = $page;
            $responseExhausted = ! empty($response['exhausted']);
            $totalCount = isset($response['totalCount']) && is_numeric($response['totalCount'])
                ? (int) $response['totalCount']
                : null;
            if ($responseExhausted || ($totalCount !== null && $totalCount === 0)) {
                $this->previewExhausted = true;
            }

            return 0;
        }

        if (isset($response['totalCount']) && is_numeric($response['totalCount'])) {
            $this->apiTotalCount = (int) $response['totalCount'];
        }

        $items = $this->filterItemsForActiveFilters($rawItems);
        $added = $this->appendUniqueResults($items);

        Log::info('AliExpress preview page loaded', [
            'page' => $page,
            'items_received' => is_array($rawItems) ? count($rawItems) : 0,
            'items_filtered' => is_array($items) ? count($items) : 0,
            'added' => $added,
            'total_loaded' => count($this->searchResults),
            'first_item_keys' => is_array($items) && isset($items[0]) ? array_keys((array) $items[0]) : [],
        ]);

        $this->previewed = true;
        $this->loadedApiPages[] = $page;
        $this->nextApiPageToFetch = $response['nextPage'] ?? ($page + 1);

        if ($added > 0) {
            $this->refreshImportedAliIds();
        }

        if (! empty($response['exhausted'])) {
            $this->previewExhausted = true;
        }

        return $added;
    }

    protected function filterItemsForActiveFilters(array $items): array
    {
        $filters = $this->activeFilters;
        $keyword = trim((string) ($filters['keyWord'] ?? ''));
        $min = isset($filters['min']) && is_numeric($filters['min']) ? (float) $filters['min'] : null;
        $max = isset($filters['max']) && is_numeric($filters['max']) ? (float) $filters['max'] : null;
        $minRating = isset($filters['minRating']) && is_numeric($filters['minRating']) ? (float) $filters['minRating'] : null;
        $categoryId = $filters['categoryId'] ?? null;
        $inStockOnly = ! empty($filters['inStockOnly']);
        $filtered = [];

        foreach ($items as $item) {
            $record = is_array($item) ? $item : (array) $item;

            if ($keyword !== '') {
                $title = (string) ($record['title'] ?? $record['productTitle'] ?? $record['subject'] ?? $record['product_title'] ?? '');
                if ($title === '' || ! Str::contains($title, $keyword, true)) {
                    continue;
                }
            }

            if ($categoryId) {
                $candidate = $record['categoryId'] ?? $record['category_id'] ?? $record['ali_category_id'] ?? null;
                if ($candidate !== null && (string) $candidate !== (string) $categoryId) {
                    continue;
                }
            }

            $price = $record['offer_sale_price']
                ?? $record['offerSalePrice']
                ?? $record['salePrice']
                ?? $record['price']
                ?? $record['targetSalePrice']
                ?? null;

            if ($price !== null && is_numeric($price)) {
                $priceValue = (float) $price;
                if ($min !== null && $priceValue < $min) {
                    continue;
                }
                if ($max !== null && $priceValue > $max) {
                    continue;
                }
            }

            if ($minRating !== null) {
                $rating = $record['feedbackScore'] ?? $record['score'] ?? $record['ratings'] ?? null;
                if ($rating !== null && is_numeric($rating) && (float) $rating < $minRating) {
                    continue;
                }
            }

            if ($inStockOnly) {
                $stock = $record['stock'] ?? $record['stock_on_hand'] ?? $record['sku_available_stock'] ?? null;
                if ($stock !== null && is_numeric($stock) && (int) $stock <= 0) {
                    continue;
                }
            }

            $filtered[] = $record;
        }

        return array_values($filtered);
    }

    protected function ensureLoadedForUiPage(int $uiPage): void
    {
        if ($uiPage < 1) {
            return;
        }

        $perPage = $this->resolveTablePerPage();
        $required = $uiPage * $perPage;
        $autoFetched = 0;
        $remaining = max(0, $required - count($this->searchResults));
        $effectivePerPage = max(1, min(20, $this->apiPageSize));
        $pagesNeeded = (int) ceil($remaining / $effectivePerPage);
        $maxPages = max($this->maxAutoFetchPages, $pagesNeeded);

        while (count($this->searchResults) < $required && ! $this->previewExhausted && $autoFetched < $maxPages) {
            $added = $this->fetchNextApiPage();
            if ($added <= 0) {
                break;
            }
            $autoFetched++;
        }
    }

    protected function paginatePreviewResults(): LengthAwarePaginator
    {
        $perPage = $this->resolveTablePerPage();
        $page = max(1, (int) $this->getTablePage());

        $this->ensureLoadedForUiPage($page);

        $items = collect($this->searchResults);
        $sliced = $items->slice(($page - 1) * $perPage, $perPage)->values();

        $total = $this->apiTotalCount ?? $items->count();
        $total = max($items->count(), $total);

        return new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $this->getTablePaginationPageName(),
            ]
        );
    }

    protected function emptyPaginatedResults(): LengthAwarePaginator
    {
        $perPage = $this->resolveTablePerPage();

        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            1,
            [
                'path' => request()->url(),
                'pageName' => $this->getTablePaginationPageName(),
            ]
        );
    }

    protected function getRecordIndex(array $record): string
    {
        $id = $this->getRecordId($record);
        if ($id === '') {
            return '-';
        }

        $index = collect($this->searchResults)
            ->values()
            ->search(fn ($item) => $this->getRecordId((array) $item) === $id);

        return $index === false ? '-' : (string) ($index + 1);
    }

    protected function getRecordKey(array $record): string
    {
        $mainProductId = $record['main_product_id'] ?? data_get($record, 'product_id_converter_result.main_product_id');
        if (! empty($mainProductId)) {
            return (string) $mainProductId;
        }

        return $this->getRecordId($record);
    }

    protected function appendUniqueResults(array $items): int
    {
        $existing = [];
        foreach ($this->searchResults as $record) {
            $key = $this->getRecordKey((array) $record);
            if ($key === '') {
                $key = md5(json_encode($record));
            }
            if ($key !== '') {
                $existing[$key] = true;
            }
        }

        $added = 0;
        foreach ($items as $item) {
            $item = $this->sanitizeRecord((array) $item);
            $key = $this->getRecordKey($item);
            if ($key === '') {
                $key = md5(json_encode($item));
            }

            if ($key !== '' && isset($existing[$key])) {
                continue;
            }

            $this->searchResults[] = $item;
            if ($key !== '') {
                $existing[$key] = true;
            }
            $added++;
        }

        return $added;
    }

    protected function sanitizeRecord(array $record): array
    {
        $encoded = json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return [];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function getCurrentPageRecords(): array
    {
        $paginator = $this->paginatePreviewResults();

        return array_map(fn ($item) => (array) $item, $paginator->items());
    }

    protected function ensureAliExpressToken(): ?AliExpressToken
    {
        $token = AliExpressToken::getLatestToken();

        if (! $token) {
            $this->notify('warning', 'Not Authenticated', 'Authenticate first.');

            return null;
        }

        if ($token->isExpired()) {
            $this->notify('warning', 'Token Expired', 'Re-authenticate.');

            return null;
        }

        return $token;
    }

    protected function refreshImportedAliIds(): void
    {
        $ids = collect($this->searchResults)
            ->map(fn ($record) => $this->getRecordId((array) $record))
            ->filter()
            ->values()
            ->unique()
            ->all();

        if ($ids === []) {
            $this->importedAliIds = collect();

            return;
        }

        $this->importedAliIds = \App\Domain\Products\Models\Product::query()
            ->whereIn('attributes->ali_item_id', $ids)
            ->get(['attributes'])
            ->map(fn (\App\Domain\Products\Models\Product $product) => (string) data_get($product->attributes, 'ali_item_id'))
            ->filter(fn ($value) => $value !== '')
            ->unique();
    }

    protected function getRecordId(array $record): string
    {
        $candidates = [
            $record['itemId'] ?? null,
            $record['productId'] ?? null,
            $record['item_id'] ?? null,
            $record['product_id'] ?? null,
            $record['id'] ?? null,
            data_get($record, 'product_id_converter_result.main_product_id'),
            data_get($record, 'main_product_id'),
        ];

        foreach ($candidates as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    protected function getImportedAliIds(): \Illuminate\Support\Collection
    {
        return $this->importedAliIds ??= collect();
    }

    protected function isImportedRecord(array $record): bool
    {
        $id = $this->getRecordId($record);

        return $id !== '' && $this->getImportedAliIds()->contains($id);
    }
}
