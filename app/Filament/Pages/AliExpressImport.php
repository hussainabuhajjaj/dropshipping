<?php

namespace App\Filament\Pages;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Services\AliExpressCategorySyncService;
use App\Filament\Pages\Concerns\InteractsWithAliExpressImportReview;
use App\Filament\Pages\Concerns\InteractsWithAliExpressImportSearch;
use App\Models\AliExpressToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class AliExpressImport extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;
    use InteractsWithAliExpressImportSearch;
    use InteractsWithAliExpressImportReview;

    private const API_PAGE_LIMIT = 40;
    private const DEFAULT_PAGE_SIZE = 20;
    private const DEFAULT_SHIP_TO_COUNTRY = 'AE';
    private const DEFAULT_TARGET_CURRENCY = 'USD';
    private const DEFAULT_TARGET_LANGUAGE = 'en_US';
    private const DEFAULT_CITY = 'Dubai';
    private const DEFAULT_PROVINCE = 'Dubai';

    public ?int $ali_category_id = null;
    public ?string $keyword = null;
    public ?float $min_price = null;
    public ?float $max_price = null;
    public string $min_rating = '0';
    public bool $in_stock_only = false;
    public ?int $page_size = 40;
    public string $ship_to_country = self::DEFAULT_SHIP_TO_COUNTRY;
    public string $target_currency = self::DEFAULT_TARGET_CURRENCY;
    public string $target_language = self::DEFAULT_TARGET_LANGUAGE;
    public bool $remove_personal_benefit = false;
    public bool $deliverable_only = false;
    public ?string $biz_model = null;
    public ?string $province_code = self::DEFAULT_PROVINCE;
    public ?string $city_code = self::DEFAULT_CITY;
    public int $apiPageSize = 40;
    public ?int $apiTotalCount = null;
    public int $nextApiPageToFetch = 1;
    public int $maxAutoFetchPages = 3;
    public array $searchResults = [];
    public bool $previewed = false;
    public array $selectedProductIds = [];
    public ?array $importPreview = null;
    public array $importForm = [];
    protected ?Collection $importedAliIds = null;
    protected array $activeFilters = [];
    protected string $activeFiltersHash = '';
    protected array $loadedApiPages = [];
    protected bool $previewExhausted = false;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'AliExpress Import';
    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 50;
    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.aliexpress-import';

    public function mount(): void
    {
        $this->importedAliIds = collect();
        $this->syncActiveFiltersFromProperties();
        $this->resetPreviewState();
    }

    public function getTitle(): string|Htmlable
    {
        return 'AliExpress Integration';
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function getTableRecordKey($record): string
    {
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

                    Grid::make(1)->schema([
                        \Filament\Forms\Components\Toggle::make('deliverable_only')
                            ->label('Deliverable to destination only')
                            ->helperText('Disabled for preview performance. Deliverability is validated when opening the product import review.')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),
                    ]),

                    Section::make('Product request parameters')
                        ->description('These values are sent to AliExpress when opening the product preview and when confirming the import.')
                        ->schema([
                            Grid::make(3)->schema([
                                \Filament\Forms\Components\TextInput::make('ship_to_country')
                                    ->label('Ship to country')
                                    ->required()
                                    ->placeholder('CN')
                                    ->maxLength(8)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                                \Filament\Forms\Components\TextInput::make('target_currency')
                                    ->label('Target currency')
                                    ->placeholder('USD')
                                    ->maxLength(8)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                                \Filament\Forms\Components\Select::make('target_language')
                                    ->label('Target language')
                                    ->options($this->getAliExpressLanguageOptions())
                                    ->searchable()
                                    ->allowHtml(false)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),
                            ]),

                            Grid::make(3)->schema([
                                \Filament\Forms\Components\TextInput::make('province_code')
                                    ->label('Province code')
                                    ->placeholder('Guangdong')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                                \Filament\Forms\Components\TextInput::make('city_code')
                                    ->label('City code')
                                    ->placeholder('Guangzhou')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),

                                \Filament\Forms\Components\TextInput::make('biz_model')
                                    ->label('Business model')
                                    ->placeholder('BETA model if required')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),
                            ]),

                            Grid::make(1)->schema([
                                \Filament\Forms\Components\Toggle::make('remove_personal_benefit')
                                    ->label('Remove personal benefit')
                                    ->helperText('If enabled, AliExpress should not apply crowd-type promotion benefits.')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->refreshPreviewFromForm()),
                            ]),
                        ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->records(fn () => $this->previewed ? $this->paginatePreviewResults() : $this->emptyPaginatedResults())
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
                    ->getStateUsing(fn (array $record) => $record['title'] ?? $record['productTitle'] ?? $record['subject'] ?? $record['product_title'] ?? '—')
                    ->searchable(),
                TextColumn::make('salePrice')
                    ->label('Sale')
                    ->badge()
                    ->getStateUsing(fn (array $record) => $record['targetSalePrice'] ?? $record['salePrice'] ?? $record['offer_sale_price'] ?? $record['price'] ?? null)
                    ->formatStateUsing(fn ($state, array $record) => filled($state)
                        ? (($record['targetOriginalPriceCurrency'] ?? $record['salePriceCurrency'] ?? $record['currency'] ?? 'USD') . ' ' . $state)
                        : '—'),
                TextColumn::make('discount')->label('Discount')->badge()->toggleable(),
                TextColumn::make('score')->label('Score')->toggleable(),
                TextColumn::make('orders')->label('Orders')->toggleable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (array $record) => $this->isImportedRecord($record) ? 'Imported' : 'New')
                    ->colors([
                        'success' => fn ($state) => $state === 'Imported',
                        'primary' => fn ($state) => $state === 'New',
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
                    ->label('Import')
                    ->color('success')
                    ->visible(fn (array $record) => ! $this->isImportedRecord($record))
                    ->action(fn (array $record) => $this->openImportPreview($record)),
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
        if (! empty($imageList) && is_string($imageList)) {
            $images = array_values(array_filter(array_map(
                fn ($url) => $this->normalizeUrl(trim($url)),
                explode(';', $imageList)
            )));
        }

        $mainImage = $this->normalizeUrl($record['itemMainPic'] ?? null);
        if ($mainImage && ! in_array($mainImage, $images, true)) {
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
        $roots = $categories->filter(fn (Category $category) => $category->parent_id === null || ! isset($idSet[$category->parent_id]))
            ->sortBy('name')
            ->values();

        $options = [];
        $walk = function (Category $category, string $prefix) use (&$walk, $byParent, &$options): void {
            $options[(string) $category->ali_category_id] = $prefix . $category->name;
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

            if (! $token) {
                $this->notify('warning', 'Not Authenticated', 'Authenticate first.');
                return;
            }

            if ($token->isExpired()) {
                $this->notify('warning', 'Token Expired', 'Re-authenticate.');
                return;
            }

            $categories = app(AliExpressCategorySyncService::class)->syncCategories();
            $this->notify('success', 'Categories Synced ✓', 'Synced ' . count($categories) . ' categories.', true);
        } catch (\Exception $e) {
            Log::error('Category sync failed', ['error' => $e->getMessage()]);
            $this->notify('danger', 'Sync Failed ✗', $e->getMessage(), true);
        }
    }

    protected function getAliExpressTimestampMillis(): string
    {
        return (string) round(microtime(true) * 1000);
    }

    public function refreshToken(): void
    {
        try {
            $token = AliExpressToken::getLatestToken();

            if (! $token) {
                $this->notify('warning', 'No Token', 'Authenticate first.');
                return;
            }

            if (! $token->canRefresh()) {
                $this->notify('warning', 'Cannot Refresh', 'Refresh token expired.');
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

            $sign = hash('sha256', $signString . config('ali_express.client_secret'));
            $params['sign'] = strtoupper($sign);

            $url = 'https://api-sg.aliexpress.com/rest/' . ltrim($apiPath, '/') . '?' . http_build_query($params);
            $response = Http::get($url);
            $data = $response->json();

            if (! isset($data['access_token'])) {
                Log::error('AliExpress refresh token response invalid', ['status' => $response->status(), 'body' => $data]);
                throw new \Exception($data['message'] ?? $data['msg'] ?? 'Unknown error from AliExpress');
            }

            $token->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
                'raw' => json_encode($data),
            ]);

            $this->notify('success', 'Token Refreshed ✓', 'Token renewed.');
        } catch (\Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            $this->notify('danger', 'Refresh Failed ✗', $e->getMessage());
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
