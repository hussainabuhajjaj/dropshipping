<?php

namespace App\Filament\Pages;

use App\Domain\Products\Models\Category;
use App\Domain\Products\Services\AliExpressCategorySyncService;
use App\Domain\Products\Services\AliExpressProductImportService;
use App\Models\AliExpressToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Domain\Products\Models\Product;
use UnitEnum;

class AliExpressImport extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?int $ali_category_id = null;
    public ?string $keyword = null;
    public ?float $min_price = null;
    public ?float $max_price = null;
    public string $min_rating = '0';
    public bool $in_stock_only = false;
    public ?int $page_size = 20;

    /** Raw API results: products[] */
    public array $searchResults = [];

    /** When true, table shows results */
    public bool $previewed = false;

    /** Selected itemIds to import */
    public array $selectedProductIds = [];
    protected ?Collection $importedAliIds = null;

    public function mount(): void
    {
        $this->importedAliIds = collect();
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
        return (string) ($record['itemId'] ?? $record['productId'] ?? md5(json_encode($record)));
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
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('keyword')
                            ->label('Keyword')
                            ->placeholder('e.g. sneakers'),
                    ]),

                    Grid::make(3)->schema([
                        \Filament\Forms\Components\TextInput::make('min_price')
                            ->label('Min price')
                            ->numeric()
                            ->placeholder('0'),

                        \Filament\Forms\Components\TextInput::make('max_price')
                            ->label('Max price')
                            ->numeric()
                            ->placeholder('9999'),

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
                            ->label('In stock only'),

                        \Filament\Forms\Components\TextInput::make('page_size')
                            ->label('Page size')
                            ->numeric()
                            ->default(20)
                            ->minValue(1)
                            ->maxValue(1000),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->records(fn (): Collection => collect($this->previewed ? $this->searchResults : []))
            ->striped()
            ->columns([
                CheckboxColumn::make('selected')
                    ->label('')
                    ->getStateUsing(fn (array $record) => $this->isSelectedRecord($record))
                    ->toggleable(false)
                    ->action(fn (array $record) => $this->toggleSelectionFromRecord($record)),

                ImageColumn::make('itemMainPic')
                    ->label('Image')
                    ->square()
                    ->imageSize(56)
                    ->getStateUsing(fn (array $record) => $this->normalizeUrl($record['itemMainPic'] ?? null)),

                TextColumn::make('title')
                    ->label('Title')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('salePrice')
                    ->label('Sale')
                    ->badge()
                    ->getStateUsing(fn (array $record) => $record['targetSalePrice'] ?? $record['salePrice'] ?? null)
                    ->formatStateUsing(fn ($state, array $record) =>
                    filled($state)
                        ? (($record['targetOriginalPriceCurrency'] ?? $record['salePriceCurrency'] ?? 'USD') . ' ' . $state)
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
            ])
            ->toolbarActions([
                BulkAction::make('select_for_import')
                    ->label('Select for import')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Collection $records) {
                        $ids = $records
                            ->map(fn ($r) => $r['itemId'] ?? $r['productId'] ?? null)
                            ->filter()
                            ->map(fn ($v) => (string) $v)
                            ->values()
                            ->all();

                        $this->selectedProductIds = array_values(array_unique([
                            ...$this->selectedProductIds,
                            ...$ids,
                        ]));

                        Notification::make()
                            ->success()
                            ->title('Selection updated')
                            ->body('Added ' . count($ids) . ' items.')
                            ->send();
                    }),

                BulkAction::make('clear_selection')
                    ->label('Clear selection')
                    ->color('gray')
                    ->action(function () {
                        $this->selectedProductIds = [];
                        Notification::make()->title('Selection cleared')->send();
                    }),
            ]);
    }

    protected function normalizeUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, '//') ? ('https:' . $url) : $url;
    }

    protected function getCategoryOptions(): array
    {
        return Category::query()
            ->whereNotNull('ali_category_id')
            ->orderBy('name')
            ->pluck('name', 'ali_category_id')
            ->toArray();
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

            $payload = array_filter([
                'categoryId'  => isset($state['ali_category_id']) ? (int) $state['ali_category_id'] : null,
                'keyWord'     => isset($state['keyword']) ? trim((string) $state['keyword']) : null,
                'min'         => isset($state['min_price']) ? (string) $state['min_price'] : null,
                'max'         => isset($state['max_price']) ? (string) $state['max_price'] : null,
                'pageSize'    => isset($state['page_size']) ? (int) $state['page_size'] : 20,
                'pageIndex'   => 1,
                'local'       => 'en_US',
                'countryCode' => 'CI',
                'currency'    => 'USD',
            ], fn ($v) => $v !== null && $v !== '');

            $service = app(AliExpressProductImportService::class);

            $raw = $service->searchOnly($payload);

            $this->searchResults = $raw['data']['products'] ?? [];
            $this->selectedProductIds = [];
            $this->previewed = true;

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
        return (string) ($record['itemId'] ?? $record['productId'] ?? '');
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
}
