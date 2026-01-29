<?php

namespace App\Filament\Pages;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Services\AliExpressCategorySyncService;
use App\Domain\Products\Services\AliExpressProductImportService;
use App\Models\AliExpressToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Domain\Products\Models\Product;
use UnitEnum;

class AliExpressImport extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    private const API_PAGE_LIMIT = 40;

    public ?int $ali_category_id = null;
    public ?string $keyword = null;
    public ?float $min_price = null;
    public ?float $max_price = null;
    public string $min_rating = '0';
    public bool $in_stock_only = false;
    public ?int $page_size = 40;
    public int $apiPageSize = 40;
    public ?int $apiTotalCount = null;
    public int $nextApiPageToFetch = 1;
    public int $maxAutoFetchPages = 3;

    /** Raw API results: products[] */
    public array $searchResults = [];

    /** When true, table shows results */
    public bool $previewed = false;

    /** Selected itemIds to import */
    public array $selectedProductIds = [];
    protected ?Collection $importedAliIds = null;
    protected array $activeFilters = [];
    protected string $activeFiltersHash = '';
    protected array $loadedApiPages = [];
    protected bool $previewExhausted = false;

    public function mount(): void
    {
        $this->importedAliIds = collect();
        $this->applyFiltersAndReload($this->buildFiltersFromProperties(), true);
    }

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'AliExpress Import';
    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 50;
    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.aliexpress-import';

    public function getTitle(): string|Htmlable
    {
        return 'AliExpress Integration';
    }

    /**
     * Filament v4 requires this method because HasTable includes translation support.
     * If you are not using translations here, returning null is OK.
     */
    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    /**
     * ✅ This replaces Table::recordKey() which does not exist in your version.
     * It MUST return a stable unique key per row.
     */
    public function getTableRecordKey($record): string
    {
        // $record is an array from your API
        $key = $this->getRecordKey((array) $record);
        return $key !== '' ? $key : md5(json_encode($record));
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Category filters')
                ->description('Select a synced AliExpress category and apply filters before previewing or importing.')
                ->schema([
                    Grid::make(2)->schema([
                        \Filament\Forms\Components\Select::make('ali_category_id')
                            ->label('AliExpress Category')
                            ->options($this->getCategoryOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                        \Filament\Forms\Components\TextInput::make('keyword')
                            ->label('Keyword')
                            ->placeholder('e.g. sneakers')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),
                    ]),

                    Grid::make(3)->schema([
                        \Filament\Forms\Components\TextInput::make('min_price')
                            ->label('Min price')
                            ->numeric()
                            ->placeholder('0')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                        \Filament\Forms\Components\TextInput::make('max_price')
                            ->label('Max price')
                            ->numeric()
                            ->placeholder('9999')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                        \Filament\Forms\Components\Select::make('min_rating')
                            ->label('Min rating')
                            ->options([
                                '0' => 'Any',
                                '3' => '3+ stars',
                                '4' => '4+ stars',
                                '5' => '5 stars',
                            ])
                            ->default('0'),
                    ]),

                    Grid::make(2)->schema([
                        \Filament\Forms\Components\Toggle::make('in_stock_only')
                            ->label('In stock only')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                        \Filament\Forms\Components\TextInput::make('page_size')
                            ->label('Page size')
                            ->numeric()
                            ->default(40)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->helperText('API fetches at least 20, max ' . self::API_PAGE_LIMIT . ' per call')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->records(fn (): LengthAwarePaginator => $this->previewed
                ? $this->paginatePreviewResults()
                : $this->emptyPaginatedResults()
            )
            ->headerActions([
                Action::make('load_more_results')
                    ->label('Load more results')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(fn () => $this->loadMoreResults())
                    ->disabled(fn () => $this->previewExhausted),

                Action::make('select_current_page')
                    ->label('Select current page')
                    ->icon('heroicon-o-check-circle')
                    ->action(fn () => $this->selectCurrentPage()),

                Action::make('select_all_loaded')
                    ->label('Select all loaded')
                    ->icon('heroicon-o-rectangle-stack')
                    ->action(fn () => $this->selectAllLoaded()),

                Action::make('select_not_imported')
                    ->label('Select not imported')
                    ->icon('heroicon-o-funnel')
                    ->action(fn () => $this->selectOnlyNotImported()),

                Action::make('clear_selection')
                    ->label('Clear selection')
                    ->color('gray')
                    ->action(fn () => $this->clearSelection()),
            ])
            ->striped()
            ->columns([
                CheckboxColumn::make('selected')
                    ->label('')
                    ->getStateUsing(fn (array $record) => $this->isSelectedRecord($record))
                    ->toggleable(false)
                    ->action(fn (array $record) => $this->toggleSelectionFromRecord($record)),

                TextColumn::make('table_index')
                    ->label('#')
                    ->getStateUsing(fn (array $record) => $this->getRecordIndex($record))
                    ->color('secondary')
                    ->sortable(false),
                ImageColumn::make('itemMainPic')
                    ->label('Image')
                    ->square()
                    ->imageSize(200)
                    ->getStateUsing(fn (array $record) => $this->normalizeUrl(
                        $record['itemMainPic']
                        ?? $record['imageUrl']
                        ?? $record['image_url']
                        ?? $record['productMainImageUrl']
                        ?? $record['product_main_image_url']
                        ?? null
                    )),

                TextColumn::make('title')
                    ->label('Title')
                    ->wrap()
                    ->getStateUsing(fn (array $record) => $record['title']
                        ?? $record['productTitle']
                        ?? $record['subject']
                        ?? $record['product_title']
                        ?? '—')
                    ->searchable(),

                TextColumn::make('salePrice')
                    ->label('Sale')
                    ->badge()
                    ->getStateUsing(fn (array $record) => $record['targetSalePrice']
                        ?? $record['salePrice']
                        ?? $record['offer_sale_price']
                        ?? $record['price']
                        ?? null)
                    ->formatStateUsing(fn ($state, array $record) =>
                    filled($state)
                        ? (($record['targetOriginalPriceCurrency'] ?? $record['salePriceCurrency'] ?? $record['currency'] ?? 'USD') . ' ' . $state)
                        : '—'
                    ),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('score')
                    ->label('Score')
                    ->toggleable(),

                TextColumn::make('orders')
                    ->label('Orders')
                    ->toggleable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (array $record) => $this->isImportedRecord($record) ? 'Imported' : 'New')
                    ->colors([
                        'success' => fn ($state): bool => $state === 'Imported',
                        'primary' => fn ($state): bool => $state === 'New',
                    ])
                    ->sortable(),

                TextColumn::make('categoryName')
                    ->label('Category')
                    ->toggleable()
                    ->getStateUsing(fn (array $record) => $record['categoryName'] ?? $record['category_name'] ?? null),

                TextColumn::make('itemId')
                    ->label('Item ID')
                    ->copyable()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalHeading(fn (array $record) => $record['title'] ?? $record['productTitle'] ?? 'AliExpress Product')
                    ->modalContent(fn (array $record) => view('filament.pages.aliexpress-detail-slide-over', [
                        'record' => $this->buildSlideOverData($record),
                    ])),
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (array $record) => $this->normalizeUrl($record['itemUrl'] ?? null), true)
                    ->visible(fn (array $record) => filled($record['itemUrl'] ?? null)),

                Action::make('select')
                    ->label(fn (array $record) => $this->isSelectedRecord($record) ? 'Unselect' : 'Select')
                    ->icon(fn (array $record) => $this->isSelectedRecord($record) ? 'heroicon-s-x-circle' : 'heroicon-o-check')
                    ->action(fn (array $record) => $this->toggleSelectionFromRecord($record))
                    ->color(fn (array $record) => $this->isSelectedRecord($record) ? 'gray' : 'primary'),

                Action::make('import_now')
                    ->label('Import now')
                    // ->icon('heroicon-o-download')
                    ->color('success')
                    ->visible(fn (array $record) => ! $this->isImportedRecord($record))
                    ->action(fn (array $record) => $this->importSingleRecord($record)),
            ]);
    }

    protected function normalizeUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, '//') ? ('https:' . $url) : $url;
    }

    protected function buildSlideOverData(array $record): array
    {
        $title = $record['title'] ?? $record['productTitle'] ?? $record['subject'] ?? 'AliExpress Product';

        $imageList = data_get($record, 'ae_multimedia_info_dto.image_urls')
            ?? data_get($record, 'ae_multimedia.image_urls')
            ?? ($record['imageUrls'] ?? null);

        $images = [];
        if (!empty($imageList) && is_string($imageList)) {
            $images = array_values(array_filter(array_map(
                fn ($url) => $this->normalizeUrl(trim($url)),
                explode(';', $imageList)
            )));
        }

        $mainImage = $this->normalizeUrl($record['itemMainPic'] ?? null);
        if ($mainImage && !in_array($mainImage, $images, true)) {
            array_unshift($images, $mainImage);
        }

        $skuInfo = data_get($record, 'ae_item_sku_info_dtos')
            ?? data_get($record, 'ae_item_sku_info')
            ?? data_get($record, 'ae_item_sku_info_dto')
            ?? [];

        $skuInfo = is_array($skuInfo) ? $skuInfo : [];

        $prices = collect($skuInfo)
            ->map(fn ($sku) => $sku['offer_sale_price'] ?? $sku['offerSalePrice'] ?? $sku['price'] ?? null)
            ->filter()
            ->map(fn ($value) => (float) $value);

        $minPrice = $prices->min();
        $maxPrice = $prices->max();

        $fallbackPrice = $record['targetSalePrice'] ?? $record['salePrice'] ?? null;
        if ($minPrice === null && $fallbackPrice !== null) {
            $minPrice = (float) $fallbackPrice;
            $maxPrice = (float) $fallbackPrice;
        }

        $currency = $record['targetOriginalPriceCurrency']
            ?? $record['salePriceCurrency']
            ?? $record['currency']
            ?? 'USD';

        $stock = collect($skuInfo)
            ->map(fn ($sku) => $sku['sku_available_stock'] ?? $sku['stock'] ?? null)
            ->filter()
            ->sum();

        $store = data_get($record, 'ae_store_info') ?? [];
        $logistics = data_get($record, 'ae_logistics') ?? data_get($record, 'logistics_info_dto') ?? [];

        return [
            'title' => $title,
            'images' => $images,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'currency' => $currency,
            'stock' => $stock,
            'store' => [
                'name' => $store['store_name'] ?? $store['storeName'] ?? null,
                'country' => $store['store_country_code'] ?? $store['storeCountryCode'] ?? null,
                'shipping_speed' => $store['shipping_speed_rating'] ?? null,
                'communication' => $store['communication_rating'] ?? null,
                'as_described' => $store['item_as_described_rating'] ?? null,
            ],
            'delivery_time' => $logistics['delivery_time'] ?? null,
            'ship_to_country' => $logistics['ship_to_country'] ?? null,
            'variants_count' => is_array($skuInfo) ? count($skuInfo) : 0,
            'record' => $record,
        ];
    }

    protected function getCategoryOptions(): array
    {
        $categories = Category::query()
            ->whereNotNull('ali_category_id')
            ->get(['id', 'parent_id', 'name', 'ali_category_id']);

        if ($categories->isEmpty()) {
            return [];
        }

        $byParent = $categories->groupBy('parent_id');
        $idSet = $categories->pluck('id')->flip();

        $roots = $categories->filter(function (Category $category) use ($idSet): bool {
            return $category->parent_id === null || ! isset($idSet[$category->parent_id]);
        })->sortBy('name')->values();

        $options = [];
        $walk = function (Category $category, string $prefix) use (&$walk, $byParent, &$options): void {
            $label = $prefix . $category->name;
            $options[(string) $category->ali_category_id] = $label;

            $children = $byParent->get($category->id, collect())->sortBy('name');
            foreach ($children as $child) {
                $walk($child, $prefix . '-- ');
            }
        };

        foreach ($roots as $root) {
            $walk($root, '');
        }

        return $options;
    }

    public function authenticateWithAliExpress(): void
    {
        redirect(route('aliexpress.oauth.redirect'));
    }

    public function syncCategories(): void
    {
        try {
            $token = AliExpressToken::getLatestToken();

            if (!$token) {
                Notification::make()->warning()->title('Not Authenticated')->body('Authenticate first.')->send();
                return;
            }

            if ($token->isExpired()) {
                Notification::make()->warning()->title('Token Expired')->body('Re-authenticate.')->send();
                return;
            }

            $service = app(AliExpressCategorySyncService::class);
            $categories = $service->syncCategories();

            Notification::make()
                ->success()
                ->title('Categories Synced ✓')
                ->body('Synced ' . count($categories) . ' categories.')
                ->persistent()
                ->send();
        } catch (\Exception $e) {
            Log::error('Category sync failed', ['error' => $e->getMessage()]);
            Notification::make()->danger()->title('Sync Failed ✗')->body($e->getMessage())->persistent()->send();
        }
    }

    public function searchProducts(): void
    {
        try {
            if (!$this->ensureAliExpressToken()) {
                return;
            }

            $state = $this->form->getState();
            $this->page_size = isset($state['page_size']) ? max(1, (int) $state['page_size']) : 20;
            $this->apiPageSize = min(self::API_PAGE_LIMIT, max(20, $this->page_size));

            $filters = $this->buildFiltersFromState($state);
            $this->applyFiltersAndReload($filters);
//dd($filters);
            Notification::make()
                ->success()
                ->title('Preview Loaded ✓')
                ->body('Found ' . count($this->searchResults) . ' products.')
                ->send();
        } catch (\Exception $e) {
            Log::error('AliExpress preview failed', ['error' => $e->getMessage()]);
            Notification::make()->danger()->title('Preview Failed ✗')->body($e->getMessage())->send();
        }
    }

    public function importSelectedProducts(): void
    {
        try {
            if (!$this->ensureAliExpressToken()) {
                return;
            }

            if (empty($this->selectedProductIds)) {
                Notification::make()->warning()->title('No selection')->body('Select items from table.')->send();
                return;
            }

            $service = app(AliExpressProductImportService::class);

        $idsToImport = array_filter($this->selectedProductIds, fn ($id) => !$this->getImportedAliIds()->contains($id));

            if ($idsToImport === []) {
                Notification::make()->info()->title('Nothing new')->body('All selected items are already imported.')->send();
                return;
            }

            $importedCount = 0;

            foreach ($idsToImport as $itemId) {
                $product = $service->importById($itemId, ['ship_to_country' => 'CN']);
                if ($product) {
                    $importedCount++;
                }
            }
            $this->refreshImportedAliIds();
            Notification::make()
                ->success()
                ->title('Import complete ✓')
                ->body("Imported {$importedCount} products.")
                ->persistent()
                ->send();
        } catch (\Exception $e) {
            Log::error('Import selected failed', ['error' => $e->getMessage()]);
            Notification::make()->danger()->title('Import Failed ✗')->body($e->getMessage())->persistent()->send();
        }
    }

    public function loadMoreResults(): void
    {
        if (!$this->ensureAliExpressToken()) {
            return;
        }

        if ($this->previewExhausted) {
            Notification::make()->info()->title('No more results')->body('Reached the end of the dataset.')->send();
            return;
        }

        $added = $this->fetchNextApiPage();

        Notification::make()
            ->success()
            ->title('More results loaded')
            ->body("Added {$added} items.")
            ->send();
    }

    public function selectCurrentPage(): void
    {
        $records = $this->getCurrentPageRecords();
        $ids = collect($records)
            ->map(fn ($r) => $this->getRecordId($r))
            ->filter()
            ->values()
            ->all();

        $this->selectedProductIds = array_values(array_unique([
            ...$this->selectedProductIds,
            ...$ids,
        ]));

        Notification::make()
            ->success()
            ->title('Selection updated')
            ->body('Added ' . count($ids) . ' items from this page.')
            ->send();
    }

    public function selectAllLoaded(): void
    {
        $ids = collect($this->searchResults)
            ->map(fn ($r) => $this->getRecordId((array) $r))
            ->filter()
            ->values()
            ->all();

        $this->selectedProductIds = array_values(array_unique([
            ...$this->selectedProductIds,
            ...$ids,
        ]));

        Notification::make()
            ->success()
            ->title('Selection updated')
            ->body('Selected all loaded results (' . count($ids) . ').')
            ->send();
    }

    public function selectOnlyNotImported(): void
    {
        $ids = collect($this->searchResults)
            ->filter(fn ($r) => ! $this->isImportedRecord((array) $r))
            ->map(fn ($r) => $this->getRecordId((array) $r))
            ->filter()
            ->values()
            ->all();

        $this->selectedProductIds = array_values(array_unique([
            ...$this->selectedProductIds,
            ...$ids,
        ]));

        Notification::make()
            ->success()
            ->title('Selection updated')
            ->body('Selected ' . count($ids) . ' not-imported items.')
            ->send();
    }

    public function clearSelection(): void
    {
        $this->selectedProductIds = [];
        Notification::make()->title('Selection cleared')->send();
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
        $state = $this->form?->getState() ?? [];
        $raw = $state['page_size'] ?? $this->page_size ?? 20;

        return max(1, (int) $raw);
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
            'inStockOnly' => !empty($state['in_stock_only']) ? true : null,
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
        ];
    }

    protected function applyFiltersAndReload(array $filters, bool $force = false): void
    {
        $hash = md5(json_encode($filters));
        if (! $force && $hash === $this->activeFiltersHash) {
            return;
        }
        $this->activeFilters = $filters;
        $this->activeFiltersHash = $hash;
        $this->apiPageSize = min(self::API_PAGE_LIMIT, max(20, (int) ($this->page_size ?? 20)));
        $this->resetPreviewState();
        $this->selectedProductIds = [];
        $this->fetchNextApiPage();
        $this->ensureLoadedForUiPage(1);
    }

    protected function refreshPreviewFromForm(): void
    {
        $state = $this->form->getState();
        $this->page_size = isset($state['page_size']) ? max(1, (int) $state['page_size']) : 20;
        $this->apiPageSize = min(self::API_PAGE_LIMIT, max(20, $this->page_size));
        $filters = $this->buildFiltersFromState($state);
        $this->applyFiltersAndReload($filters, true);
    }

    public function updatedAliCategoryId(): void
    {
        $this->applyFiltersAndReload($this->buildFiltersFromProperties());
    }

    public function updatedKeyword(): void
    {
        $this->applyFiltersAndReload($this->buildFiltersFromProperties());
    }

    public function updatedMinPrice(): void
    {
        $this->applyFiltersAndReload($this->buildFiltersFromProperties());
    }

    public function updatedMaxPrice(): void
    {
        $this->applyFiltersAndReload($this->buildFiltersFromProperties());
    }

    public function updatedInStockOnly(): void
    {
        $this->applyFiltersAndReload($this->buildFiltersFromProperties());
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
            'local' => 'en_US',
            'countryCode' => 'CN',
            'currency' => 'USD',
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

        if (!$this->ensureAliExpressToken()) {
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
//        dd($added);
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
//dd($this->nextApiPageToFetch);
        if ($added > 0) {
            $this->refreshImportedAliIds();
        }

        if (!empty($response['exhausted'])) {
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

        return array_values(array_filter($items, function ($item) use ($keyword, $min, $max, $minRating, $categoryId, $inStockOnly): bool {
            $record = is_array($item) ? $item : (array) $item;

            if ($keyword !== '') {
                $title = (string) (
                    $record['title']
                    ?? $record['productTitle']
                    ?? $record['subject']
                    ?? $record['product_title']
                    ?? ''
                );
                if ($title === '' || ! Str::contains($title, $keyword, true)) {
                    return false;
                }
            }

            if ($categoryId) {
                $candidate = $record['categoryId']
                    ?? $record['category_id']
                    ?? $record['ali_category_id']
                    ?? null;
                if ($candidate !== null && (string) $candidate !== (string) $categoryId) {
                    return false;
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
                    return false;
                }
                if ($max !== null && $priceValue > $max) {
                    return false;
                }
            }

            if ($minRating !== null) {
                $rating = $record['feedbackScore']
                    ?? $record['score']
                    ?? $record['ratings']
                    ?? null;
                if ($rating !== null && is_numeric($rating) && (float) $rating < $minRating) {
                    return false;
                }
            }

            if ($inStockOnly) {
                $stock = $record['stock']
                    ?? $record['stock_on_hand']
                    ?? $record['sku_available_stock']
                    ?? null;
                if ($stock !== null && is_numeric($stock) && (int) $stock <= 0) {
                    return false;
                }
            }

            return true;
        }));
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
        $sliced = $items
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

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
        if (!empty($mainProductId)) {
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

        if (!$token) {
            Notification::make()->warning()->title('Not Authenticated')->body('Authenticate first.')->send();
            return null;
        }

        if ($token->isExpired()) {
            Notification::make()->warning()->title('Token Expired')->body('Re-authenticate.')->send();
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

        $this->importedAliIds = Product::query()
            ->whereIn('attributes->ali_item_id', $ids)
            ->get(['attributes'])
            ->map(fn (Product $product) => (string) data_get($product->attributes, 'ali_item_id'))
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

    protected function getImportedAliIds(): Collection
    {
        return $this->importedAliIds ??= collect();
    }

    protected function isSelectedRecord(array $record): bool
    {
        $id = $this->getRecordId($record);
        return $id !== '' && in_array($id, $this->selectedProductIds, true);
    }

    protected function isImportedRecord(array $record): bool
    {
        $id = $this->getRecordId($record);
        return $id !== '' && $this->getImportedAliIds()->contains($id);
    }

    protected function toggleSelectionFromRecord(array $record): void
    {
        $id = $this->getRecordId($record);

        if ($id === '') {
            return;
        }

        if ($this->isImportedRecord($record)) {
            Notification::make()
                ->warning()
                ->title('Already imported')
                ->body("Item {$id} exists.")
                ->send();
            return;
        }

        if ($this->isSelectedRecord($record)) {
            $this->selectedProductIds = array_values(array_filter(
                $this->selectedProductIds,
                fn ($value) => $value !== $id
            ));
            Notification::make()->info()->title('Selection updated')->body("Item {$id} removed.")->send();
            return;
        }

        $this->selectedProductIds[] = $id;
        Notification::make()->success()->title('Selected')->body("Item {$id} added.")->send();
    }

    protected function importSingleRecord(array $record): void
    {
        if (!$this->ensureAliExpressToken()) {
            return;
        }

        $id = $this->getRecordId($record);

        if ($id === '') {
            Notification::make()->warning()->title('Invalid record')->body('Missing AliExpress ID.')->send();
            return;
        }

        if ($this->isImportedRecord($record)) {
            Notification::make()->info()->title('Already imported')->body("Item {$id} exists.")->send();
            return;
        }

        $service = app(AliExpressProductImportService::class);
        $product = $service->importById($id, ['ship_to_country' => 'CN']);

        if ($product) {
            Notification::make()->success()->title('Imported')->body("Item {$id} imported.")->send();
        } else {
            Notification::make()->danger()->title('Import failed')->body("Item {$id} could not be imported.")->send();
        }

        $this->refreshImportedAliIds();
        $this->selectedProductIds = array_values(array_filter(
            $this->selectedProductIds,
            fn ($value) => $value !== $id
        ));
    }


    protected function getAliExpressTimestampMillis(): string
    {
        return (string) round(microtime(true) * 1000);
    }

    public function refreshToken(): void
    {
        try {
            $token = AliExpressToken::getLatestToken();

            if (!$token) {
                Notification::make()->warning()->title('No Token')->body('Authenticate first.')->send();
                return;
            }

            if (!$token->canRefresh()) {
                Notification::make()->warning()->title('Cannot Refresh')->body('Refresh token expired.')->send();
                return;
            }

            $apiPath = '/auth/token/create';

            $params = [
                'client_id' => config('ali_express.client_id'),
                'refresh_token' => $token->refresh_token,
                'sign_method' => 'sha256',
                'timestamp' => $this->getAliExpressTimestampMillis(),
            ];

            ksort($params);

            $signString = $apiPath;
            foreach ($params as $key => $value) {
                $signString .= $key . $value;
            }

            $appSecret = config('ali_express.client_secret');
            $sign = hash('sha256', $signString . $appSecret);
            $params['sign'] = strtoupper($sign);

            $url = 'https://api-sg.aliexpress.com/rest/' . ltrim($apiPath, '/') . '?' . http_build_query($params);

            $response = Http::get($url);
            $data = $response->json();

            if (!isset($data['access_token'])) {
                Log::error('AliExpress refresh token response invalid', ['status' => $response->status(), 'body' => $data]);
                throw new \Exception($data['message'] ?? $data['msg'] ?? 'Unknown error from AliExpress');
            }

            $token->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            Notification::make()->success()->title('Token Refreshed ✓')->body('Token renewed.')->send();
        } catch (\Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            Notification::make()->danger()->title('Refresh Failed ✗')->body($e->getMessage())->send();
        }
    }

    public function getToken(): ?AliExpressToken
    {
        try {
            return AliExpressToken::query()->latest()->first();
        } catch (\Exception $e) {
            Log::warning('Could not fetch AliExpress token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getLoadedCount(): int
    {
        return count($this->searchResults);
    }

    public function getLoadedApiPageCount(): int
    {
        return count($this->loadedApiPages);
    }

    public function getSelectedCount(): int
    {
        return count($this->selectedProductIds);
    }

    public function getImportedCount(): int
    {
        return $this->getImportedAliIds()->count();
    }
}
