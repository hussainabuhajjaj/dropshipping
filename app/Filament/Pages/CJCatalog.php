<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\CjProductMediaService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\ImportCjProductJob;
use App\Jobs\SyncCjProductsJob;
use App\Models\CjCatalogFilterPreset;
use App\Services\Api\ApiException;
use App\Services\Cj\CjCatalogImportTracker;
use App\Traits\LogsUserActivity;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\ArrayRecord;
use Filament\Support\Contracts\TranslatableContentDriver;
use App\Filament\Pages\BasePage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class CJCatalog extends BasePage implements HasTable
{
    use InteractsWithTable;
    use LogsUserActivity;

    private const DEFAULT_PAGE_SIZE = 24;
    private const MIN_PAGE_SIZE = 10;
    private const MAX_PAGE_SIZE = 200;
    private const MAX_BULK_IMPORT_FROM_PAGE = 15;
    private const MY_PRODUCTS_FETCH_SIZE = 50;
    private const LISTED_PRODUCTS_FETCH_SIZE = 200;
    private const ALLOWED_SORTS = ['1', '2', '5', '6'];
    private const PRESET_MAX_COUNT = 20;
    private const IMPORT_TRACKING_POLL_SECONDS = 3; // Near real-time for production

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 91;
    protected string $view = 'filament.pages.cj-catalog';

    public ?array $products = null;
    public array $items = [];
    public array $existingCatalog = [];
    public int $pageNum = 1;
    public int $pageSize = self::DEFAULT_PAGE_SIZE;
    public int $total = 0;
    public int $totalPages = 1;
    public bool $totalPagesKnown = false;
    public int $loaded = 0;
    public int $inventoryTotal = 0;
    public int $withImages = 0;
    public ?string $avgPrice = null;
    public int $lastBatchCount = 0;
    public bool $canLoadMore = false;
    public ?string $imagePreviewPid = null;
    public ?string $imagePreviewUrl = null;
    public ?string $imagePreviewName = null;
    public array $imagePreviewUrls = [];
    public array $videoPreviewUrls = [];

    public ?string $productName = null;
    public ?string $productSku = null;
    public ?string $materialKey = null;
    public ?string $categoryId = null;
    public array $categoryOptions = [];
    public ?string $warehouseId = null;
    public array $warehouseOptions = [];
    public bool $warehouseLoadFailed = false;
    public bool $inStockOnly = false;
    public string $sort = '';
    public ?string $storeProductId = null;
    public ?string $shipToCountry = null;
    public ?string $categorySearch = null;

    public ?string $presetName = null;
    public ?int $selectedPresetId = null;
    public array $presetOptions = [];

    public ?string $activeImportTrackingKey = null;
    /** @var array<string, mixed> */
    public array $queueImportStatus = [];
    public bool $queueImportCompletionNotified = false;

    public int $syncEnabledCount = 0;
    public int $syncDisabledCount = 0;
    public int $syncStaleCount = 0;
    public ?string $lastCommandMessage = null;
    public ?string $lastCommandAt = null;

    /**
     * HasTable includes translation support in Filament v4.
     */
    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\CJCatalogStatsWidget::class,
        ];
    }

    /**
     * Pass page state into header widgets using documented Filament API.
     *
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return array_merge(parent::getWidgetData(), [
            'total' => $this->total,
            'pageNum' => $this->pageNum,
            'totalPages' => $this->totalPages,
            'totalPagesKnown' => $this->totalPagesKnown,
            'avgPrice' => $this->avgPrice,
            'loaded' => $this->loaded,
            'inventoryTotal' => $this->inventoryTotal,
            'withImages' => $this->withImages,
            'syncEnabledCount' => $this->syncEnabledCount,
            'syncDisabledCount' => $this->syncDisabledCount,
            'syncStaleCount' => $this->syncStaleCount,
        ]);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getImportPollIntervalSeconds(): int
    {
        return self::IMPORT_TRACKING_POLL_SECONDS;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection): array => $this->buildTableRecords($search, $sortColumn, $sortDirection))
            ->headerActions($this->tableHeaderActions())
            ->columns($this->tableColumns())
            ->recordActions($this->tableRecordActions())
            ->bulkActions($this->tableBulkActions())
            ->striped()
            ->selectable()
            ->paginated(false)
            ->defaultKeySort(false)
            ->emptyStateHeading('No CJ products found')
            ->emptyStateDescription('Adjust your filters or refresh the catalog.');
    }

    /**
     * @return array<int, Action>
     */
    private function tableHeaderActions(): array
    {
        return [
            Action::make('syncListedCjProducts')
                ->label('Sync Listed CJ Products')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(fn (): mixed => $this->syncListedCjProducts()),
            Action::make('setShipTo')
                ->label('Ship-to Filter')
                ->icon('heroicon-o-globe-alt')
                ->color('secondary')
                ->schema([
                    TextInput::make('country')
                        ->label('Country code (e.g., US, GB)')
                        ->maxLength(2)
                        ->default(strtoupper((string) (config('services.cj.ship_to_default') ?? ''))),
                ])
                ->action(function (array $data): void {
                    $this->shipToCountry = $this->normalizeCountryCode($data['country'] ?? null);
                    $this->flushCachedTableRecords();
                }),
            Action::make('clearShipTo')
                ->label('Clear Ship-to')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action(function (): void {
                    $this->shipToCountry = null;
                    $this->flushCachedTableRecords();
                }),
        ];
    }

    /**
     * @return array<int, TextColumn|ImageColumn>
     */
    private function tableColumns(): array
    {
        return [
            ImageColumn::make('image')
                    ->label('Image')
                    ->getStateUsing(fn (array $record): ?string => $this->recordImage($record))
                    ->square()
                    ->imageSize(48)
                    ->action(function (array $record): void {
                        $this->showImagePreview(
                            $this->recordImage($record),
                            $this->recordName($record),
                            $this->recordPid($record),
                        );
                    })
                    ->extraImgAttributes(function (array $record): array {
                        return $this->recordImage($record)
                            ? ['class' => 'cursor-zoom-in']
                            : ['class' => 'cursor-default'];
                    }),
                TextColumn::make('name')
                    ->label('Product')
                    ->getStateUsing(fn (array $record): string => $this->recordName($record))
                    ->description(fn (array $record): string => $this->recordSubline($record))
                    ->wrap()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Category')
                    ->getStateUsing(fn (array $record): ?string => $this->recordCategory($record))
                    ->placeholder('--')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->getStateUsing(fn (array $record): ?float => $this->recordPrice($record))
                    ->money('USD')
                    ->placeholder('--')
                    ->sortable(),
                TextColumn::make('inventory')
                    ->label('Inventory')
                    ->getStateUsing(fn (array $record): ?int => $this->recordInventory($record))
                    ->badge()
                    ->color(fn (array $record): string => $this->recordInventoryColor($record))
                    ->placeholder('n/a')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (array $record): string => $this->recordStatusLabel($record))
                    ->badge()
                    ->color(fn (array $record): string => $this->recordStatusColor($record))
                    ->sortable(),
                TextColumn::make('synced_at')
                    ->label('Last synced')
                    ->getStateUsing(fn (array $record): ?string => $this->recordSyncedAt($record))
                    ->placeholder('--')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pid')
                    ->label('PID')
                    ->getStateUsing(fn (array $record): string => $this->recordPid($record))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->getStateUsing(fn (array $record): ?string => $this->recordSku($record))
                    ->placeholder('--')
                    ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Action>
     */
    private function tableRecordActions(): array
    {
        return [
            Action::make('importPipeline')
                ->label('Import (Pipeline)')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->form([
                    TextInput::make('margin')
                        ->label('Margin %')
                        ->numeric()
                        ->default(config('services.cj.import_margin', 35))
                        ->required()
                        ->minValue(0)
                        ->maxValue(200)
                        ->helperText('Markup percentage to apply on cost price'),
                    Toggle::make('enrich')
                        ->label('Fetch full details')
                        ->default(true)
                        ->helperText('Calls CJ API for complete product data (slower but accurate)'),
                    Toggle::make('auto_activate')
                        ->label('Auto-activate if valid')
                        ->default(true)
                        ->helperText('Activate product if it passes validation'),
                ])
                ->action(function (array $record, array $data): void {
                    $this->importWithPipeline($this->recordPid($record), $data);
                })
                ->requiresConfirmation()
                ->modalHeading('Import with Full Pipeline')
                ->modalDescription(fn (array $record) =>
                    "Import {$this->recordName($record)} with automatic pricing, validation, and activation."
                )
                ->visible(fn (array $record): bool => $this->recordPid($record) !== ''),
            Action::make('import')
                ->label('Import (Legacy)')
                ->color('gray')
                ->action(function (array $record): void {
                    $this->import($this->recordPid($record));
                })
                ->requiresConfirmation()
                ->visible(fn (array $record): bool => $this->recordPid($record) !== ''),
            Action::make('add_to_my')
                ->label(label: 'Add to My')
                ->color('gray')
                ->action(function (array $record): void {
                    $this->addToMyProducts($this->recordPid($record));
                })
                ->visible(fn (array $record): bool => $this->recordPid($record) !== ''),
            Action::make('stock')
                ->label('Stock')
                ->color('gray')
                ->action(function (array $record): void {
                    $this->checkStock($this->recordPid($record));
                })
                ->visible(fn (array $record): bool => $this->recordPid($record) !== ''),
            Action::make('edit')
                ->label('Edit')
                ->color('gray')
                ->url(fn (array $record): ?string => $this->recordEditUrl($record))
                ->openUrlInNewTab()
                ->visible(fn (array $record): bool => $this->recordEditUrl($record) !== null),
        ];
    }

    /**
     * @return array<int, BulkAction>
     */
    private function tableBulkActions(): array
    {
        return [
            BulkAction::make('importPipeline')
                ->label('Import with Pipeline')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->modalHeading('Bulk Import Products')
                ->modalDescription('Configure import settings for selected products')
                ->modalWidth('2xl')
                ->form([
                    Section::make('Pricing Settings')
                        ->schema([
                            TextInput::make('margin')
                                ->label('Margin %')
                                ->numeric()
                                ->default(config('services.cj.import_margin', 60))
                                ->required()
                                ->minValue(0)
                                ->maxValue(200)
                                ->suffix('%')
                                ->helperText('Markup percentage to apply on cost price (recommended: 60%)'),
                            Toggle::make('apply_to_variants')
                                ->label('Apply margin to all variants')
                                ->default(true)
                                ->helperText('Apply the same margin to all product variants'),
                        ])
                        ->columns(2),

                    Section::make('Import Options')
                        ->schema([
                            Toggle::make('enrich')
                                ->label('Fetch full product details')
                                ->default(true)
                                ->helperText('Get complete product info, images, and variants from CJ API')
                                ->reactive(),
                            Toggle::make('auto_activate')
                                ->label('Auto-activate if valid')
                                ->default(true)
                                ->helperText('Automatically activate products that pass validation'),
                            Toggle::make('skip_existing')
                                ->label('Skip already imported products')
                                ->default(false)
                                ->helperText('Only import products that don\'t exist in your catalog'),
                        ])
                        ->columns(3),

                    Section::make('Category & Organization')
                        ->schema([
                            \Filament\Forms\Components\Select::make('default_category_id')
                                ->label('Default Category (Optional)')
                                ->options(function () {
                                    return \App\Models\Category::pluck('name', 'id')->toArray();
                                })
                                ->searchable()
                                ->helperText('Assign products to this category if CJ category mapping fails'),
                        ]),

                    Section::make('Translation & SEO')
                        ->schema([
                            Toggle::make('queue_translations')
                                ->label('Queue translations')
                                ->default(true)
                                ->helperText('Automatically translate products to configured languages'),
                            Toggle::make('queue_seo')
                                ->label('Generate SEO metadata')
                                ->default(true)
                                ->helperText('Automatically generate SEO titles and descriptions'),
                        ])
                        ->columns(2),

                    Section::make('Batch Processing')
                        ->schema([
                            \Filament\Forms\Components\Select::make('batch_size')
                                ->label('Batch Size')
                                ->options([
                                    '5' => '5 products (Very Safe)',
                                    '10' => '10 products (Safe)',
                                    '25' => '25 products (Balanced)',
                                    '50' => '50 products (Fast)',
                                    '100' => '100 products (Very Fast)',
                                ])
                                ->default('25')
                                ->helperText('Products processed per background job. Higher = faster but more memory usage'),
                        ]),
                ])
                ->action(function (Collection $records, array $data): void {
                    $pids = $this->selectedPids($records);
                    $this->bulkImportWithPipeline($pids, $data);
                })
                ->modalSubmitActionLabel('Import Selected Products')
                ->modalCancelActionLabel('Cancel'),
            BulkAction::make('importSelected')
                ->label('Queue import (Legacy)')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (Collection $records): void {
                    $pids = $this->selectedPids($records);
                    $this->queueImportByPids($pids, 'selected products');
                }),
            BulkAction::make('addSelected')
                ->label('Add selection to My Products')
                ->icon('heroicon-o-plus')
                ->color('secondary')
                ->action(function (Collection $records): void {
                    $this->addSelectedToMyProducts($records);
                }),

            BulkAction::make('previewPricing')
                ->label('Preview Pricing')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->modalHeading('Preview Pricing for Selected Products')
                ->modalWidth('3xl')
                ->schema([
                    TextInput::make('preview_margin')
                        ->label('Margin %')
                        ->numeric()
                        ->default(60)
                        ->required()
                        ->suffix('%')
                        ->reactive(),
                ])
                ->action(function (Collection $records, array $data): void {
                    $pids = $this->selectedPids($records);
                    $this->showPricingPreview($pids, $data['preview_margin']);
                })
                ->modalSubmitActionLabel('Close'),

            BulkAction::make('checkInventory')
                ->label('Check Stock Levels')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->action(function (Collection $records): void {
                    $pids = $this->selectedPids($records);
                    $this->bulkCheckInventory($pids);
                }),

            BulkAction::make('exportSelection')
                ->label('Export to CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (Collection $records): void {
                    $pids = $this->selectedPids($records);
                    $this->exportSelectionToCsv($records);
                }),
        ];
    }

    public function getStatsData(): array
    {
        return [
            [
                'label' => 'Total Products',
                'value' => number_format($this->total),
                'icon' => 'heroicon-o-cube',
            ],
            [
                'label' => 'Current Page',
                'value' => "Page {$this->pageNum} of " . ($this->totalPagesKnown ? $this->totalPages : '--'),
                'icon' => 'heroicon-o-bookmark',
            ],
            [
                'label' => 'Average Price',
                'value' => $this->avgPrice ? '$' . $this->avgPrice : '--',
                'icon' => 'heroicon-o-currency-dollar',
            ],
            [
                'label' => 'Items Loaded',
                'value' => number_format($this->loaded),
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'Total Inventory',
                'value' => number_format($this->inventoryTotal),
                'icon' => 'heroicon-o-archive-box',
            ],
            [
                'label' => 'With Images',
                'value' => number_format($this->withImages),
                'icon' => 'heroicon-o-photo',
            ],
            [
                'label' => 'Sync Enabled',
                'value' => number_format($this->syncEnabledCount),
                'icon' => 'heroicon-o-arrow-path',
            ],
            [
                'label' => 'Sync Disabled',
                'value' => number_format($this->syncDisabledCount),
                'icon' => 'heroicon-o-pause-circle',
            ],
            [
                'label' => 'Stale Products',
                'value' => number_format($this->syncStaleCount),
                'icon' => 'heroicon-o-exclamation-circle',
            ],
        ];
    }

    public function mount(): void
    {
        $this->shipToCountry = $this->normalizeCountryCode(config('services.cj.ship_to_default'));
        $this->loadCategories();
        $this->loadWarehouses();
        $this->loadPresetOptions();
        $this->hydrateActiveImportStatus();
        $this->fetch();
    }

    private function loadCategories(): void
    {
        try {
            $resp = $this->catalogClient()->listCategories();
            $tree = $resp->data ?? [];
            $this->categoryOptions = $this->flattenCategories(is_array($tree) ? $tree : []);
        } catch (\Throwable $e) {
            $this->categoryOptions = [];
        }
    }

    private function loadWarehouses(): void
    {
        $this->warehouseLoadFailed = false;
        try {
            $service = app(\App\Domain\Fulfillment\Services\CJWarehouseService::class);
            $options = $service->getWarehouseOptions();
            if (! is_array($options) || empty($options)) {
                $this->warehouseLoadFailed = true;
                $this->warehouseOptions = [];
                Notification::make()
                    ->title('Could not load CJ warehouses')
                    ->body('Warehouse filter disabled until reload.')
                    ->danger()
                    ->send();
                return;
            }
            $this->warehouseOptions = $options;
        } catch (\Throwable $e) {
            $this->warehouseLoadFailed = true;
            $this->warehouseOptions = [];
            Notification::make()
                ->title('Could not load CJ warehouses')
                ->body('Warehouse filter disabled until reload.')
                ->danger()
                ->send();
        }
    }

    public function filteredCategoryOptions(): array
    {
        $search = trim((string) $this->categorySearch);
        if ($search === '') {
            return $this->categoryOptions;
        }

        $needle = Str::lower($search);

        return collect($this->categoryOptions)
            ->filter(fn (string $label): bool => Str::contains(Str::lower($label), $needle))
            ->all();
    }

    public function saveFilterPreset(): void
    {
        $userId = $this->currentUserId();
        if (! $userId) {
            $this->notifyError('No authenticated user found');
            return;
        }

        $name = trim((string) $this->presetName);
        if ($name === '') {
            Notification::make()->title('Preset name required')->warning()->send();
            return;
        }

        $count = CjCatalogFilterPreset::query()->where('user_id', $userId)->count();
        if ($count >= self::PRESET_MAX_COUNT) {
            Notification::make()->title('Preset limit reached')->body('Delete an old preset before saving a new one.')->warning()->send();
            return;
        }

        $preset = CjCatalogFilterPreset::query()->updateOrCreate(
            ['user_id' => $userId, 'name' => $name],
            ['filters' => $this->currentFilterPayload()]
        );

        $this->selectedPresetId = (int) $preset->id;
        $this->presetName = null;
        $this->loadPresetOptions();

        Notification::make()->title('Preset saved')->body($preset->name)->success()->send();
    }

    public function applySelectedPreset(): void
    {
        $userId = $this->currentUserId();
        if (! $userId || ! $this->selectedPresetId) {
            Notification::make()->title('Select a preset first')->warning()->send();
            return;
        }

        $preset = CjCatalogFilterPreset::query()
            ->where('user_id', $userId)
            ->whereKey($this->selectedPresetId)
            ->first();

        if (! $preset) {
            Notification::make()->title('Preset not found')->warning()->send();
            return;
        }

        $filters = is_array($preset->filters) ? $preset->filters : [];
        $this->applyFilterPayload($filters);
        $this->pageNum = 1;
        $this->fetch();

        Notification::make()->title('Preset applied')->body($preset->name)->success()->send();
    }

    public function deleteSelectedPreset(): void
    {
        $userId = $this->currentUserId();
        if (! $userId || ! $this->selectedPresetId) {
            Notification::make()->title('Select a preset first')->warning()->send();
            return;
        }

        $preset = CjCatalogFilterPreset::query()
            ->where('user_id', $userId)
            ->whereKey($this->selectedPresetId)
            ->first();

        if (! $preset) {
            Notification::make()->title('Preset not found')->warning()->send();
            return;
        }

        $deletedName = $preset->name;
        $preset->delete();

        $this->selectedPresetId = null;
        $this->loadPresetOptions();
        Notification::make()->title('Preset deleted')->body($deletedName)->success()->send();
    }

    public function refreshQueueImportStatus(): void
    {
        if (! $this->activeImportTrackingKey) {
            return;
        }

        $status = $this->importTracker()->get($this->activeImportTrackingKey);
        if (! is_array($status)) {
            return;
        }

        $this->queueImportStatus = $status;

        $isDone = in_array((string) ($status['status'] ?? ''), ['completed', 'completed_with_failures', 'failed'], true);
        if ($isDone && ! $this->queueImportCompletionNotified) {
            $failed = (int) ($status['failed'] ?? 0);
            $total = (int) ($status['total'] ?? 0);
            $success = (int) ($status['success'] ?? 0);

            $notification = Notification::make()
                ->title($failed > 0 ? 'Import completed with failures' : 'Import completed')
                ->body("Processed {$total}, success {$success}, failed {$failed}.")
                ->seconds(8);

            if ($failed > 0) {
                $notification->warning();
            } else {
                $notification->success();
            }

            $notification->send();
            $this->queueImportCompletionNotified = true;
            $this->fetch(notify: false);
            
            // Auto-clear tracking after completion to stop polling
            $this->activeImportTrackingKey = null;
        }
    }

    public function fetch(bool $append = false, bool $notify = true): void
    {
        try {
            $this->normalizePagination();
            $sort = $this->normalizeSortValue($this->sort);

            $filters = [
                'pageNum' => $this->pageNum,
                'pageSize' => $this->pageSize,
                'categoryId' => $this->categoryId,
                'productSku' => $this->productSku,
                'productName' => $this->productName,
                'materialKey' => $this->materialKey,
                'storeProductId' => $this->storeProductId,
                'warehouseId' => $this->warehouseId,
                'haveStock' => $this->inStockOnly ? 1 : null,
                'sort' => $sort,
            ];

            $client = $this->catalogClient();
            $resp = $this->storeProductId
                ? $client->listMyProducts($filters)
                : $client->listProducts($filters);
            $this->products = $resp->data ?? null;
            $this->hydrateResults($append);
            if ($notify) {
                $this->notifySuccess($append ? 'Loaded more CJ products' : 'Loaded CJ catalog');
            }
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function import(string $pid): void
    {
        try {
            $product = $this->importService()->importByPid($pid, $this->defaultImportOptions());
            if (! $product) {
                $this->notifyError('CJ product not found');
                return;
            }

            $this->notifySuccess("Imported {$product->name}");
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function addToMyProducts(string $pid): void
    {
        try {
            $this->catalogClient()->addToMyProducts($pid);
            $this->notifySuccess('Added to My Products');
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function checkStock(string $pid): void
    {
        try {
            $resp = $this->catalogClient()->getStockByPid($pid);
            $total = $this->sumStorage($resp->data ?? null);
            Notification::make()->title('CJ stock')->body("PID {$pid}: {$total}")->send();
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function showImagePreview(?string $url, ?string $name = null, ?string $pid = null): void
    {
        if (! $url && ! $pid) {
            return;
        }

        $previousPid = $this->imagePreviewPid;
        $this->imagePreviewPid = $pid;
        $this->imagePreviewUrl = $url;
        $this->imagePreviewName = $name;
        $shouldReload = $pid && $pid !== $previousPid;

        if ($pid && ($shouldReload || $this->imagePreviewUrls === [])) {
            $this->imagePreviewUrls = $url ? [$url] : [];
            $this->videoPreviewUrls = [];
            $this->loadProductMedia($pid, $url);
        } elseif ($url && ! in_array($url, $this->imagePreviewUrls, true)) {
            array_unshift($this->imagePreviewUrls, $url);
        }

        $this->dispatch('open-modal', id: $this->getImagePreviewModalId());
    }

    public function closeImagePreview(): void
    {
        $this->imagePreviewUrl = null;
        $this->imagePreviewName = null;
        $this->imagePreviewPid = null;
        $this->imagePreviewUrls = [];
        $this->videoPreviewUrls = [];
    }

    public function setActivePreviewImage(string $url): void
    {
        $this->imagePreviewUrl = $url;
    }

    private function loadProductMedia(string $pid, ?string $fallbackUrl = null): void
    {
        try {
            $client = $this->catalogClient();
            $productResp = $client->getProduct($pid);
            $productData = $productResp->data ?? null;
            if (! is_array($productData)) {
                return;
            }

            $variants = [];
            try {
                $variantResp = $client->getVariantsByPid($pid);
                $variants = is_array($variantResp->data ?? null) ? $variantResp->data : [];
            } catch (ApiException) {
                $variants = [];
            }

            $mediaService = app(CjProductMediaService::class);
            $images = $mediaService->extractImageUrls($productData, $variants);
            $videos = $mediaService->extractVideoUrls($productData, $variants);

            if ($fallbackUrl && ! in_array($fallbackUrl, $images, true)) {
                array_unshift($images, $fallbackUrl);
            }

            $this->imagePreviewUrls = $images;
            $this->videoPreviewUrls = $videos;

            if (! $this->imagePreviewUrl && $images !== []) {
                $this->imagePreviewUrl = $images[0];
            }
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    private function flattenCategories(array $nodes, string $prefix = ''): array
    {
        $options = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            // CJ API shape: categoryFirstList -> categorySecondList -> categoryId/categoryName
            $id = $node['id']
                ?? $node['categoryId']
                ?? $node['categoryid']
                ?? null;

            $name = $node['name']
                ?? $node['categoryName']
                ?? $node['categoryname']
                ?? $node['categoryFirstName']
                ?? $node['categorySecondName']
                ?? null;

            if ($id !== null && $name !== null) {
                $label = $prefix ? $prefix . ' › ' . $name : $name;
                $options[$id] = $label;
            }

            $children = $node['children']
                ?? $node['childNode']
                ?? $node['child']
                ?? $node['categoryFirstList']
                ?? $node['categorySecondList']
                ?? [];
            if (is_array($children) && $children !== []) {
                $options += $this->flattenCategories($children, $prefix ? $prefix . ' › ' . ($name ?? '') : ($name ?? ''));
            }
        }

        return $options;
    }

    public function getImagePreviewModalId(): string
    {
        return $this->getId() . '-image-preview';
    }

    private function sumStorage(mixed $payload): int
    {
        $total = 0;
        $add = function ($value) use (&$total) {
            if (is_numeric($value)) {
                $total += (int) $value;
            }
        };

        if (is_numeric($payload)) {
            $add($payload);
            return $total;
        }

        if (is_array($payload)) {
            if (array_key_exists('storageNum', $payload)) {
                $add($payload['storageNum']);
            }

            foreach ($payload as $entry) {
                if (is_array($entry) && array_key_exists('storageNum', $entry)) {
                    $add($entry['storageNum']);
                } elseif (is_array($entry)) {
                    foreach ($entry as $deep) {
                        if (is_array($deep) && array_key_exists('storageNum', $deep)) {
                            $add($deep['storageNum']);
                        }
                    }
                }
            }
        }

        return $total;
    }

    public function applyFilters(): void
    {
        $this->normalizePagination();
        $this->fetch();
    }

    public function resetFilters(): void
    {
        $this->productName = null;
        $this->productSku = null;
        $this->materialKey = null;
        $this->categoryId = null;
        $this->categorySearch = null;
        $this->warehouseId = null;
        $this->inStockOnly = false;
        $this->sort = '';
        $this->storeProductId = null;
        $this->pageNum = 1;
        $this->pageSize = self::DEFAULT_PAGE_SIZE;
        $this->fetch();
    }

    public function nextPage(): void
    {
        if (! $this->canLoadMore) {
            return;
        }

        $this->pageNum++;
        $this->fetch();
    }

    public function loadMore(): void
    {
        if (! $this->canLoadMore) {
            return;
        }

        $this->pageNum++;
        $this->fetch(true, false);
    }

    public function previousPage(): void
    {
        if ($this->pageNum <= 1) {
            return;
        }

        $this->pageNum--;
        $this->fetch();
    }

    public function importDisplayedProducts(): void
    {
        $batch = array_slice($this->items, 0, self::MAX_BULK_IMPORT_FROM_PAGE);
        $pids = collect($batch)
            ->map(fn ($record) => $this->recordPid($record))
            ->filter()
            ->values()
            ->all();

        $this->bulkImportByPids($pids, 'from this page');
    }

    public function queueImportDisplayedProducts(): void
    {
        $batch = array_slice($this->items, 0, self::MAX_BULK_IMPORT_FROM_PAGE);
        $pids = collect($batch)
            ->map(fn ($record) => $this->recordPid($record))
            ->filter()
            ->values()
            ->all();

        $this->queueImportByPids($pids, 'current page');
    }

    public function queueSyncJob(): void
    {
        SyncCjProductsJob::dispatch($this->pageNum, $this->pageSize);
        $message = "Queued sync job for page {$this->pageNum}.";
        Notification::make()->title('CJ sync job')->body($message)->success()->send();
        $this->recordCommandMessage($message);
    }

    public function importMyProductsNow(): void
    {
        try {
            $client = $this->catalogClient();
            $resp = $client->listMyProducts([
                'pageNum' => 1,
                'pageSize' => self::MY_PRODUCTS_FETCH_SIZE,
            ]);

            [, $list] = $this->resolveCatalogPayload((array) ($resp->data ?? []));
            $count = $this->importPids(
                collect($list)->map(fn (array $record): string => $this->recordPid($record))->filter()->values()->all(),
            );

            $message = $count > 0 ? "Imported {$count} of your CJ My Products." : 'No CJ My Products were imported.';
            Notification::make()->title('My Products')->body($message)->success()->send();
            $this->recordCommandMessage($message);
            $this->fetch();
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function importSelected(Collection $records): void
    {

        $pids = $this->selectedPids($records);
        $this->bulkImportByPids($pids, 'from your selection');
    }

    public function queueImportSelectedProducts(): void
    {
        $selected = $this->getSelectedTableRecords();
        $pids = $this->selectedPids($selected);

        if ($pids === [] && ! empty($this->selectedTableRecords)) {
            $pids = collect($this->selectedTableRecords)
                ->filter(fn (mixed $key): bool => is_string($key) || is_numeric($key))
                ->map(fn (mixed $key): string => trim((string) $key))
                ->filter(fn (string $pid): bool => $pid !== '')
                ->values()
                ->all();
        }

        $this->queueImportByPids($pids, 'selected products');
    }

    /**
     * Queue import by explicit table row keys sent from the UI.
     *
     * @param  array<int, mixed>  $keys
     */
    public function queueImportSelectedByKeys(array $keys): void
    {
        $pids = collect($keys)
            ->filter(fn (mixed $key): bool => is_string($key) || is_numeric($key))
            ->map(fn (mixed $key): string => trim((string) $key))
            ->filter(fn (string $pid): bool => $pid !== '')
            ->unique()
            ->values()
            ->all();

        $this->queueImportByPids($pids, 'selected products');
    }

    public function retryFailedQueuedImports(): void
    {
        $failedPids = collect($this->queueImportStatus['failed_pids'] ?? [])
            ->filter(fn (mixed $pid): bool => is_string($pid) && $pid !== '')
            ->values()
            ->all();

        if ($failedPids === []) {
            Notification::make()->title('No failed PID to retry')->warning()->send();
            return;
        }

        $this->queueImportByPids($failedPids, 'retry failed products');
    }

    public function addSelectedToMyProducts(Collection $records): void
    {
        $pids = $this->selectedPids($records);
        if ($pids === []) {
            Notification::make()->title('Bulk add')->body('No CJ products selected.')->warning()->send();
            return;
        }

        $this->bulkAddToMyProducts($pids);
    }

    public function importWithPipeline(string $pid, array $options): void
    {
        try {
            $importService = app(\App\Domain\Products\Services\CjProductImportService::class);
            $result = $importService->importBulkWithPipeline([
                'pids' => [$pid],
                'margin_percent' => (float) ($options['margin'] ?? 35),
                'enrich' => (bool) ($options['enrich'] ?? true),
                'force_activate' => (bool) ($options['auto_activate'] ?? true),
            ]);

            if ($result['activated'] > 0) {
                $this->notifySuccess("Product imported and activated with {$options['margin']}% margin");
            } elseif ($result['imported'] > 0) {
                $errors = $result['activation_errors'][$pid] ?? [];
                if (empty($errors)) {
                    $errorMsg = 'Product imported successfully but validation details not available. Check product in admin panel.';
                } else {
                    $errorMsg = implode(', ', $errors);
                }
                Notification::make()
                    ->title('Product imported but not activated')
                    ->body($errorMsg)
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                $this->notifyError('Import failed');
            }

            $this->fetch();
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    public function bulkImportWithPipeline(array $pids, array $options): void
    {
        if (empty($pids)) {
            Notification::make()->title('No products selected')->warning()->send();
            return;
        }

        // Log bulk import initiation
        \App\Models\UserActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'cj.import.bulk.initiated',
            'description' => 'Initiated bulk import of ' . count($pids) . ' products from CJ Catalog',
            'properties' => [
                'product_count' => count($pids),
                'options' => $options,
                'pids_sample' => array_slice($pids, 0, 5),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);

        try {
            // Filter out already imported products if skip_existing is enabled
            if ($options['skip_existing'] ?? false) {
                $existingPids = \App\Models\Product::whereIn('cj_pid', $pids)
                    ->pluck('cj_pid')
                    ->toArray();
                $pids = array_diff($pids, $existingPids);

                if (empty($pids)) {
                    \App\Models\UserActivityLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'cj.import.bulk.skipped',
                        'description' => 'All ' . count($existingPids) . ' selected products already imported',
                        'properties' => ['skipped_count' => count($existingPids)],
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);
                    Notification::make()
                        ->title('All products already imported')
                        ->body('All selected products are already in your catalog')
                        ->warning()
                        ->send();
                    return;
                }
            }

            // PERFORMANCE OPTIMIZATION: Dispatch to background job instead of synchronous processing
            $batchSize = (int) ($options['batch_size'] ?? 10);
            $chunks = array_chunk($pids, $batchSize);
            
            // Create tracking key for progress monitoring
            $trackingKey = 'cj_bulk_import_' . auth()->id() . '_' . time();
            
            // Initialize tracking using the existing tracker method
            $this->importTracker()->set($trackingKey, [
                'status' => 'queued',
                'total' => count($pids),
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
                'started_at' => now()->toISOString(),
                'chunks_total' => count($chunks),
                'chunks_completed' => 0,
            ]);

            // Dispatch chunks as jobs for parallel processing
            foreach ($chunks as $index => $chunk) {
                \App\Jobs\ImportCjProductPipelineChunkJob::dispatch($chunk, [
                    'margin_percent' => (float) ($options['margin'] ?? 60),
                    'enrich' => (bool) ($options['enrich'] ?? true),
                    'force_activate' => (bool) ($options['auto_activate'] ?? true),
                    'skip_translations' => !(bool) ($options['queue_translations'] ?? true),
                    'skip_seo' => !(bool) ($options['queue_seo'] ?? true),
                    'default_category_id' => $options['default_category_id'] ?? null,
                    'tracking_key' => $trackingKey,
                    'chunk_index' => $index,
                ])->onQueue('cj-import');
            }

            // Set active tracking for UI polling using the proper method
            $this->activeImportTrackingKey = $trackingKey;
            $this->queueImportCompletionNotified = false;

            // Immediate success notification - no blocking!
            Notification::make()
                ->title('Import Started')
                ->body(sprintf('Processing %d products in background. You can continue working.', count($pids)))
                ->success()
                ->seconds(5)
                ->send();

        } catch (\Throwable $e) {
            \Log::error('Bulk import dispatch failed', [
                'error' => $e->getMessage(),
                'pids_count' => count($pids),
                'user_id' => auth()->id(),
            ]);
            
            Notification::make()
                ->title('Import Failed to Start')
                ->body('Could not start import process. Please try again.')
                ->danger()
                ->send();
        }
    }

    private function hydrateResults(bool $append = false): void
    {
        $payload = $this->products ?? [];
        [$content, $batchItems] = $this->resolveCatalogPayload($payload);
        $this->lastBatchCount = count($batchItems);

        $this->items = $append
            ? $this->mergeItems($this->items, $batchItems)
            : $batchItems;

        $totalValue = $this->extractNumeric($content, $payload, ['total', 'totalCount', 'totalElements', 'totalRecords']);
        if ($totalValue !== null) {
            $this->total = $totalValue;
        } elseif (! $append) {
            $this->total = count($this->items);
        }

        $pageNumValue = $this->extractNumeric($content, $payload, ['pageNum', 'page', 'current', 'pageNumber']) ?? $this->pageNum ?? 1;
        $pageSizeValue = $this->extractNumeric($content, $payload, ['pageSize', 'size', 'limit']) ?? $this->pageSize ?? self::DEFAULT_PAGE_SIZE;

        $this->pageNum = max(1, $pageNumValue);
        $this->pageSize = max(self::MIN_PAGE_SIZE, $pageSizeValue);

        $totalPagesValue = $this->extractNumeric($content, $payload, ['totalPages', 'totalPage', 'pages']);
        if ($totalPagesValue !== null) {
            $this->totalPages = max(1, $totalPagesValue);
            $this->totalPagesKnown = true;
        } elseif ($totalValue !== null && $this->pageSize > 0) {
            $this->totalPages = (int) ceil($totalValue / $this->pageSize);
            $this->totalPagesKnown = true;
        } else {
            $this->totalPages = max(1, $this->pageNum);
            $this->totalPagesKnown = false;
        }

        $this->loaded = count($this->items);
        $this->canLoadMore = $this->totalPagesKnown
            ? $this->pageNum < $this->totalPages
            : $this->lastBatchCount >= $this->pageSize;

        $priceValues = collect($this->items)
            ->pluck('sellPrice')
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        $this->avgPrice = $priceValues->count() ? number_format($priceValues->avg(), 2) : null;
        $this->inventoryTotal = collect($this->items)
            ->map(fn (array $item) => $this->recordInventory($item))
            ->filter(fn ($value) => is_numeric($value))
            ->sum();
        $this->withImages = collect($this->items)
            ->filter(fn ($item) => ! empty($item['bigImg'] ?? $item['productImage'] ?? $item['productImg'] ?? $item['bigImage'] ?? null))
            ->count();

        $this->existingCatalog = [];
        $pids = collect($this->items)
            ->map(fn (array $item) => $this->recordPid($item))
            ->filter()
            ->values()
            ->all();

        if ($pids !== []) {
            $this->existingCatalog = Product::query()
                ->whereIn('cj_pid', $pids)
                ->get(['id', 'cj_pid', 'cj_sync_enabled', 'cj_synced_at', 'name'])
                ->keyBy('cj_pid')
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sync_enabled' => (bool) $product->cj_sync_enabled,
                    'synced_at' => $product->cj_synced_at?->toDateTimeString(),
                ])
                ->all();
        }

        $this->updateSyncCounts();

        $this->flushCachedTableRecords();
    }

    private function mergeItems(array $existing, array $incoming): array
    {
        if ($existing === []) {
            return $incoming;
        }

        $merged = [];
        $seen = [];

        foreach (array_merge($existing, $incoming) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $pid = $this->recordPid($item);
            $key = $pid !== '' ? $pid : md5(json_encode($item, JSON_UNESCAPED_SLASHES));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = $item;
        }

        return $merged;
    }

    private function extractNumeric(array $primary, array $fallback, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $primary) && is_numeric($primary[$key])) {
                return (int) $primary[$key];
            }
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $fallback) && is_numeric($fallback[$key])) {
                return (int) $fallback[$key];
            }
        }

        return null;
    }

    private function buildTableRecords(?string $search, ?string $sortColumn, ?string $sortDirection): array
    {
        $records = collect($this->items ?? []);

        // Apply pre-import ship-to filtering using best-effort warehouse inference
        $shipTo = $this->shipToCountry;
        if ($shipTo) {
            $records = $records->filter(function (array $record) use ($shipTo): bool {
                $codes = $this->recordWarehouseCountries($record);
                // If we cannot infer any warehouses, do not exclude
                return $codes === [] || in_array($shipTo, $codes, true);
            });
        }

        if ($search) {
            $needle = Str::lower($search);
            $records = $records->filter(function (array $record) use ($needle): bool {
                $haystack = Str::lower(implode(' ', array_filter([
                    $this->recordName($record),
                    $this->recordPid($record),
                    $this->recordSku($record),
                    $this->recordCategory($record),
                    $record['materialKey'] ?? null,
                ])));

                return Str::contains($haystack, $needle);
            });
        }

        $column = $sortColumn ?: 'inventory';
        $direction = $sortDirection === 'asc' ? 'asc' : 'desc';

        $records = $records->sortBy(
            fn (array $record): mixed => $this->sortValue($record, $column),
            SORT_REGULAR,
            $direction === 'desc'
        );

        return $records
            ->values()
            ->map(function (array $record): array {
                $pid = $this->recordPid($record);
                $record[ArrayRecord::getKeyName()] = $pid !== '' ? $pid : md5(json_encode($record, JSON_UNESCAPED_SLASHES));
                return $record;
            })
            ->all();
    }

    private function recordWarehouseCountries(array $record): array
    {
        $candidates = [];

        $lists = [
            $record['warehouseList'] ?? null,
            $record['warehouseInfo'] ?? null,
            $record['warehouse'] ?? null,
            $record['warehouses'] ?? null,
        ];

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $code = $item['countryCode']
                    ?? $item['country']
                    ?? $item['warehouseCountryCode']
                    ?? $item['warehouseCountry']
                    ?? null;

                if (is_string($code) && $code !== '') {
                    $candidates[] = strtoupper($code);
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    public function getTableRecordKey($record): string
    {
        if (is_array($record)) {
            $keyName = ArrayRecord::getKeyName();

            if (isset($record[$keyName])) {
                return (string) $record[$keyName];
            }

            $pid = $this->recordPid($record);

            if ($pid !== '') {
                return $pid;
            }
        }

        return md5(json_encode($record, JSON_UNESCAPED_SLASHES));
    }

    private function sortValue(array $record, string $column): mixed
    {
        return match ($column) {
            'name' => $this->recordName($record),
            'pid' => $this->recordPid($record),
            'sku' => $this->recordSku($record) ?? '',
            'category' => $this->recordCategory($record) ?? '',
            'price' => $this->recordPrice($record) ?? 0,
            'inventory' => $this->recordInventory($record) ?? -1,
            'status' => $this->recordStatusRank($record),
            'synced_at' => $this->recordSyncedAtSort($record),
            default => '',
        };
    }

    private function recordName(array $record): string
    {
        return (string) ($record['productNameEn']
            ?? $record['productName']
            ?? $record['nameEn']
            ?? $record['name']
            ?? 'CJ product');
    }

    private function recordPid(array $record): string
    {
        return (string) ($record['pid']
            ?? $record['productId']
            ?? $record['product_id']
            ?? $record['id']
            ?? '');
    }

    private function recordSku(array $record): ?string
    {
        $sku = $record['productSku'] ?? $record['sku'] ?? null;
        return $sku !== '' ? $sku : null;
    }

    private function recordCategory(array $record): ?string
    {
        // API returns threeCategoryName (3rd level), twoCategoryName (2nd level), oneCategoryName (1st level)
        return $record['threeCategoryName']
            ?? $record['twoCategoryName']
            ?? $record['oneCategoryName']
            ?? $record['categoryName']      // Fallback for older API versions
            ?? $record['categoryNameEn']    // Fallback
            ?? null;
    }

    private function recordPrice(array $record): ?float
    {
        // Check all possible price fields from CJ API
        $priceFields = [
            'nowPrice',           // Current/discount price (preferred)
            'discountPrice',      // Best discount price
            'sellPrice',          // Regular sell price
            'productSellPrice',   // Alternative sell price field
        ];

        foreach ($priceFields as $field) {
            if (is_numeric($record[$field] ?? null)) {
                return (float) $record[$field];
            }
        }

        return null;
    }

    private function recordInventory(array $record): ?int
    {
        if (is_numeric($record['warehouseInventoryNum'] ?? null)) {
            return (int) $record['warehouseInventoryNum'];
        }

        if (is_numeric($record['listingCount'] ?? null)) {
            return (int) $record['listingCount'];
        }

        if (is_numeric($record['listedNum'] ?? null)) {
            return (int) $record['listedNum'];
        }

        return null;
    }

    private function recordInventoryColor(array $record): string
    {
        $inventory = $this->recordInventory($record);
        if ($inventory === null) {
            return 'gray';
        }

        return $inventory > 0 ? 'success' : 'danger';
    }

    private function recordImage(array $record): ?string
    {
        return $record['bigImg']
            ?? $record['productImage']
            ?? $record['productImg']
            ?? $record['bigImage']
            ?? null;
    }

    private function recordStatusLabel(array $record): string
    {
        $existing = $this->existingCatalog[$this->recordPid($record)] ?? null;
        if (! $existing) {
            return 'Not imported';
        }

        return $existing['sync_enabled'] ? 'Imported + sync' : 'Imported (sync off)';
    }

    private function recordStatusColor(array $record): string
    {
        $existing = $this->existingCatalog[$this->recordPid($record)] ?? null;
        if (! $existing) {
            return 'gray';
        }

        return $existing['sync_enabled'] ? 'success' : 'warning';
    }

    private function recordStatusRank(array $record): int
    {
        $existing = $this->existingCatalog[$this->recordPid($record)] ?? null;
        if (! $existing) {
            return 0;
        }

        return $existing['sync_enabled'] ? 2 : 1;
    }

    private function recordSyncedAt(array $record): ?string
    {
        $existing = $this->existingCatalog[$this->recordPid($record)] ?? null;
        return $existing['synced_at'] ?? null;
    }

    private function recordSyncedAtSort(array $record): int
    {
        $value = $this->recordSyncedAt($record);
        if (! $value) {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? $timestamp : 0;
    }

    private function recordEditUrl(array $record): ?string
    {
        $existing = $this->existingCatalog[$this->recordPid($record)] ?? null;
        if (! $existing) {
            return null;
        }

        return \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $existing['id']]);
    }

    private function recordSubline(array $record): string
    {
        $parts = [];
        $pid = $this->recordPid($record);
        if ($pid !== '') {
            $parts[] = 'PID ' . $pid;
        }
        $sku = $this->recordSku($record);
        if ($sku) {
            $parts[] = 'SKU ' . $sku;
        }

        $material = $record['materialKey'] ?? null;
        if ($material) {
            $parts[] = 'Material ' . $material;
        }

        return implode(' | ', $parts);
    }

    private function updateSyncCounts(): void
    {
        $enabled = 0;
        $disabled = 0;
        $stale = 0;
        $threshold = Carbon::now()->subDays(3);

        foreach ($this->existingCatalog as $existing) {
            if (! isset($existing['sync_enabled'])) {
                continue;
            }

            if ($existing['sync_enabled']) {
                $enabled++;
            } else {
                $disabled++;
            }

            if (! empty($existing['synced_at'])) {
                try {
                    $syncedAt = Carbon::parse($existing['synced_at']);
                } catch (\Throwable) {
                    $syncedAt = null;
                }

                if ($syncedAt && $syncedAt->lt($threshold)) {
                    $stale++;
                }
            }
        }

        $this->syncEnabledCount = $enabled;
        $this->syncDisabledCount = $disabled;
        $this->syncStaleCount = $stale;
    }

    private function recordCommandMessage(string $message): void
    {
        $this->lastCommandMessage = $message;
        $this->lastCommandAt = Carbon::now()->toDateTimeString();
    }

    private function selectedPids(Collection $records): array
    {
        return $records
            ->map(fn ($record) => $this->recordPid((array) $record))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function bulkImportByPids(array $pids, string $context): void
    {
        if ($pids === []) {
            Notification::make()->title('Bulk import')->body('No CJ products selected.')->warning()->send();
            return;
        }

        $imported = $this->importPids($pids);

        $message = $imported > 0
            ? "Imported {$imported} CJ products {$context}."
            : 'No new products were imported.';

        Notification::make()->title('Bulk import')->body($message)->success()->send();
        $this->recordCommandMessage($message);
        $this->fetch();
    }

    private function queueImportByPids(array $pids, string $context): void
    {
        $pids = array_values(array_unique(array_filter($pids, fn (mixed $pid): bool => is_string($pid) && $pid !== '')));
        if ($pids === []) {
            Notification::make()->title('Queue import')->body('No CJ products selected.')->warning()->send();
            return;
        }

        $userId = $this->currentUserId();
        if (! $userId) {
            $this->notifyError('No authenticated user found');
            return;
        }

        $trackingKey = $this->importTracker()->start($userId, $pids, $context);
        $this->activeImportTrackingKey = $trackingKey;
        $this->queueImportCompletionNotified = false;
        $this->hydrateActiveImportStatus();

        foreach ($pids as $pid) {
            ImportCjProductJob::dispatch($pid, $this->defaultImportOptions(), $trackingKey)
                ->onQueue('import');
        }

        $message = 'Queued ' . count($pids) . " products for {$context}.";
        $this->recordCommandMessage($message);
        Notification::make()->title('Queue import started')->body($message)->success()->send();
    }

    private function bulkAddToMyProducts(array $pids): void
    {
        if ($pids === []) {
            Notification::make()->title('Bulk add')->body('No CJ products selected.')->warning()->send();
            return;
        }

        $client = $this->catalogClient();
        $added = 0;

        foreach ($pids as $pid) {
            try {
                $client->addToMyProducts($pid);
            } catch (ApiException $e) {
                $this->notifyApiError($e, $pid);
                continue;
            } catch (\Throwable $e) {
                $this->notifyError($e->getMessage(), $pid);
                continue;
            }
            $added++;
        }

        $message = $added > 0
            ? "Added {$added} CJ products to My Products."
            : 'No CJ products were added.';

        Notification::make()->title('Bulk add')->body($message)->success()->send();
        $this->recordCommandMessage($message);
    }

    public function syncListedCjProducts(): void
    {
        try {
            $resp = $this->catalogClient()->listMyProducts([
                'pageNum' => 1,
                'pageSize' => self::LISTED_PRODUCTS_FETCH_SIZE,
            ]);

            $listed = $this->filterListedRecords((array) ($resp->data ?? []));
            $pids = collect($listed)
                ->map(fn (array $record): string => $this->recordPid($record))
                ->filter()
                ->values()
                ->all();

            $imported = $this->importPids($pids);
            $message = $imported > 0
                ? "Imported {$imported} listed CJ products."
                : 'No listed CJ products were imported.';

            Notification::make()->title('Listed Products')->body($message)->success()->send();
            $this->recordCommandMessage($message);
            $this->fetch();
        } catch (ApiException $e) {
            $this->notifyApiError($e);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());
        }
    }

    private function importPids(array $pids): int
    {
        $imported = 0;

        foreach ($pids as $pid) {
            try {
                $product = $this->importService()->importByPid($pid, $this->defaultImportOptions());
            } catch (ApiException $e) {
                $this->notifyApiError($e, $pid);
                continue;
            } catch (\Throwable $e) {
                $this->notifyError($e->getMessage(), $pid);
                continue;
            }

            if ($product) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filterListedRecords(array $payload): array
    {
        [, $records] = $this->resolveCatalogPayload($payload);

        return array_values(array_filter(
            $records,
            fn (mixed $item): bool => is_array($item) && (int) ($item['listedShopNum'] ?? 0) > 0
        ));
    }

    private function loadPresetOptions(): void
    {
        $userId = $this->currentUserId();
        if (! $userId) {
            $this->presetOptions = [];
            $this->selectedPresetId = null;
            return;
        }

        $this->presetOptions = CjCatalogFilterPreset::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->limit(self::PRESET_MAX_COUNT)
            ->pluck('name', 'id')
            ->map(fn (string $name): string => $name)
            ->all();

        if ($this->selectedPresetId && ! array_key_exists($this->selectedPresetId, $this->presetOptions)) {
            $this->selectedPresetId = null;
        }
    }

    private function hydrateActiveImportStatus(): void
    {
        $userId = $this->currentUserId();
        if (! $userId) {
            return;
        }

        $trackingKey = $this->importTracker()->getActiveKey($userId);
        if (! $trackingKey) {
            return;
        }

        $this->activeImportTrackingKey = $trackingKey;
        $status = $this->importTracker()->get($trackingKey);
        if (is_array($status)) {
            $this->queueImportStatus = $status;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function currentFilterPayload(): array
    {
        return [
            'productName' => $this->productName,
            'productSku' => $this->productSku,
            'materialKey' => $this->materialKey,
            'categoryId' => $this->categoryId,
            'categorySearch' => $this->categorySearch,
            'warehouseId' => $this->warehouseId,
            'inStockOnly' => $this->inStockOnly,
            'sort' => $this->sort,
            'storeProductId' => $this->storeProductId,
            'pageSize' => $this->pageSize,
            'shipToCountry' => $this->shipToCountry,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyFilterPayload(array $payload): void
    {
        $this->productName = $this->nullableString($payload['productName'] ?? null);
        $this->productSku = $this->nullableString($payload['productSku'] ?? null);
        $this->materialKey = $this->nullableString($payload['materialKey'] ?? null);
        $this->categoryId = $this->nullableString($payload['categoryId'] ?? null);
        $this->categorySearch = $this->nullableString($payload['categorySearch'] ?? null);
        $this->warehouseId = $this->nullableString($payload['warehouseId'] ?? null);
        $this->inStockOnly = (bool) ($payload['inStockOnly'] ?? false);
        $this->sort = (string) ($payload['sort'] ?? '');
        $this->storeProductId = $this->nullableString($payload['storeProductId'] ?? null);
        $this->pageSize = is_numeric($payload['pageSize'] ?? null) ? (int) $payload['pageSize'] : self::DEFAULT_PAGE_SIZE;
        $this->shipToCountry = $this->normalizeCountryCode($payload['shipToCountry'] ?? $this->shipToCountry);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function currentUserId(): ?int
    {
        $id = auth(config('filament.auth.guard', 'admin'))->id();

        return is_numeric($id) ? (int) $id : null;
    }

    private function defaultImportOptions(): array
    {
        return [
            'respectSyncFlag' => false,
            'defaultSyncEnabled' => true,
            'syncReviews' => true,
            'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
            'import_batch_id' => $this->generateImportBatchId(),
        ];
    }

    private function generateImportBatchId(): string
    {
        return 'cj_import_' . now()->format('Y-m-d_His') . '_' . Str::random(8);
    }

    private function normalizeCountryCode(mixed $country): ?string
    {
        $code = strtoupper(trim((string) ($country ?? '')));

        return $code !== '' ? $code : null;
    }

    private function normalizePagination(): void
    {
        $this->pageNum = max(1, $this->pageNum);
        $this->pageSize = min(self::MAX_PAGE_SIZE, max(self::MIN_PAGE_SIZE, $this->pageSize));
    }

    private function normalizeSortValue(?string $sort): ?string
    {
        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : null;
    }

    private function catalogClient()
    {
        return app(CJDropshippingClient::class);
    }

    private function importService()
    {
        return app(CjProductImportService::class);
    }

    private function importTracker(): CjCatalogImportTracker
    {
        return app(CjCatalogImportTracker::class);
    }

    private function notifySuccess(string $title, ?string $body = null): void
    {
        $notification = Notification::make()->title($title)->success();

        if ($body) {
            $notification->body($body);
        }

        $notification->send();
    }

    private function notifyError(string $message, ?string $pid = null): void
    {
        $body = $pid ? "{$pid}: {$message}" : $message;

        Notification::make()->title('Error')->body($body)->danger()->send();
    }

    private function notifyApiError(ApiException $exception, ?string $pid = null): void
    {
        $body = $pid ? "{$pid}: {$exception->getMessage()}" : $exception->getMessage();

        Notification::make()
            ->title('CJ error')
            ->body($body)
            ->danger()
            ->icon('heroicon-o-exclamation-circle')
            ->send();
    }

    private function resolveCatalogPayload(array $payload): array
    {
        if (isset($payload['list']) && is_array($payload['list'])) {
            return [['list' => $payload['list']], $payload['list']];
        }

        $content = $payload['content'] ?? null;

        if (is_array($content)) {
            if (array_is_list($content)) {
                $first = $content[0] ?? null;
                if (is_array($first) && array_key_exists('productList', $first)) {
                    $items = is_array($first['productList'] ?? null) ? $first['productList'] : [];
                    return [$first, $items];
                }

                if ($content !== [] && is_array($content[0] ?? null)) {
                    return [['productList' => $content], $content];
                }
            } elseif (array_key_exists('productList', $content)) {
                $items = is_array($content['productList'] ?? null) ? $content['productList'] : [];
                return [$content, $items];
            }
        }

        if (isset($payload['productList']) && is_array($payload['productList'])) {
            return [['productList' => $payload['productList']], $payload['productList']];
        }

        return [[], []];
    }

    /**
     * Show pricing preview for selected products
     */
    public function showPricingPreview(array $pids, float $margin): void
    {
        if (empty($pids)) {
            Notification::make()->title('No products selected')->warning()->send();
            return;
        }

        // Log pricing preview
        \App\Models\UserActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'cj.pricing.preview',
            'description' => 'Previewed pricing for ' . count($pids) . ' products with ' . $margin . '% margin',
            'properties' => [
                'product_count' => count($pids),
                'margin' => $margin,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $previewData = [];
        $totalCost = 0;
        $totalSelling = 0;

        foreach (array_slice($pids, 0, 10) as $pid) {
            // Find product in current items
            $record = collect($this->items)->firstWhere(fn($item) => $this->recordPid($item) === $pid);
            if (!$record) continue;

            $costPrice = $this->recordPrice($record);
            if (!$costPrice) continue;

            $sellingPrice = round($costPrice * (1 + $margin / 100), 2);
            $profit = $sellingPrice - $costPrice;

            $previewData[] = [
                'name' => substr($this->recordName($record), 0, 40),
                'cost' => $costPrice,
                'selling' => $sellingPrice,
                'profit' => $profit,
            ];

            $totalCost += $costPrice;
            $totalSelling += $sellingPrice;
        }

        $message = "Pricing Preview ({$margin}% margin)\n\n";
        foreach ($previewData as $item) {
            $message .= sprintf(
                "%s\nCost: $%.2f → Selling: $%.2f (Profit: $%.2f)\n\n",
                $item['name'],
                $item['cost'],
                $item['selling'],
                $item['profit']
            );
        }

        if (count($pids) > 10) {
            $message .= sprintf("... and %d more products\n\n", count($pids) - 10);
        }

        $message .= sprintf(
            "Total (first 10): Cost $%.2f → Selling $%.2f",
            $totalCost,
            $totalSelling
        );

        Notification::make()
            ->title('Pricing Preview')
            ->body($message)
            ->info()
            ->persistent()
            ->send();
    }

    /**
     * Check inventory for selected products using real-time CJ API
     */
    public function bulkCheckInventory(array $pids): void
    {
        if (empty($pids)) {
            Notification::make()->title('No products selected')->warning()->send();
            return;
        }

        // Log inventory check
        \App\Models\UserActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'cj.inventory.check.initiated',
            'description' => 'Started inventory check for ' . count($pids) . ' products',
            'properties' => ['product_count' => count($pids)],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $inStock = 0;
        $outOfStock = 0;
        $lowStock = 0;
        $errors = 0;
        $stockDetails = [];

        $client = $this->catalogClient();

        // Check real-time stock for each product via CJ API
        foreach ($pids as $pid) {
            try {
                // Get product variants first
                $productResponse = $client->getProduct($pid);
                $productData = $productResponse->data ?? null;

                if (!$productData) {
                    $errors++;
                    continue;
                }

                // Get variants from product data
                $variants = $productData['variants'] ?? $productData['variantList'] ?? [];
                $totalStock = 0;

                // Check stock for each variant using queryByVid
                foreach ($variants as $variant) {
                    $vid = $variant['vid'] ?? null;
                    if (!$vid) continue;

                    try {
                        // Use getStockByVid which calls queryByVid endpoint
                        $stockResponse = $client->getStockByVid($vid);
                        $stockData = $stockResponse->data ?? [];

                        // Parse response according to CJ API docs
                        // data is an array of warehouse stock info
                        foreach ($stockData as $warehouseStock) {
                            // totalInventoryNum is the total available stock
                            $stock = $warehouseStock['totalInventoryNum'] ?? 
                                     $warehouseStock['storageNum'] ?? 
                                     $warehouseStock['cjInventoryNum'] ?? 0;
                            $totalStock += (int) $stock;
                        }
                    } catch (\Exception $e) {
                        // Skip variant if error
                        \Illuminate\Support\Facades\Log::debug('CJ stock check failed for VID', [
                            'vid' => $vid,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                }

                // Categorize stock level
                if ($totalStock === 0) {
                    $outOfStock++;
                } elseif ($totalStock < 10) {
                    $lowStock++;
                } else {
                    $inStock++;
                }

                $stockDetails[$pid] = $totalStock;

            } catch (\Exception $e) {
                $errors++;
                \Illuminate\Support\Facades\Log::warning('CJ stock check failed for PID', [
                    'pid' => $pid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = sprintf(
            "In Stock: %d | Low Stock (<10): %d | Out of Stock: %d",
            $inStock,
            $lowStock,
            $outOfStock
        );

    if ($errors > 0) {
        $message .= sprintf(" | Errors: %d", $errors);
    }

    // Log inventory check results
    \App\Models\UserActivityLog::create([
        'user_id' => auth()->id(),
        'action' => 'cj.inventory.check.completed',
        'description' => sprintf('Inventory check: %d in stock, %d low stock, %d out of stock', 
            $inStock, $lowStock, $outOfStock),
        'properties' => [
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_checked' => count($pids),
        ],
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);

        if ($outOfStock > 0 || $errors > 0) {
            Notification::make()
                ->title('Stock Check - Warning')
                ->body($message)
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Stock Check - All Good')
                ->body($message)
                ->success()
                ->send();
        }
    }

    /**
     * Export selected products to CSV
     */
    public function exportSelectionToCsv(Collection $records): void
    {
        // Log export initiation
        \App\Models\UserActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'cj.export.csv.initiated',
            'description' => 'Started CSV export of ' . $records->count() . ' products',
            'properties' => ['record_count' => $records->count()],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $data = [];

        foreach ($records as $record) {
            $data[] = [
                'PID' => $this->recordPid($record),
                'SKU' => $this->recordSku($record) ?? '',
                'Name' => $this->recordName($record),
                'Category' => $this->recordCategory($record) ?? '',
                'Price' => $this->recordPrice($record) ?? 0,
                'Inventory' => $this->recordInventory($record) ?? 0,
            ];
        }

        $filename = 'cj-catalog-export-' . date('Y-m-d-His') . '.csv';
        $filepath = storage_path('app/public/' . $filename);

        $file = fopen($filepath, 'w');
        fputcsv($file, ['PID', 'SKU', 'Name', 'Category', 'Price', 'Inventory']);

        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        // Log export completion
        \App\Models\UserActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'cj.export.csv.completed',
            'description' => 'Exported ' . count($data) . ' products to ' . $filename,
            'properties' => [
                'product_count' => count($data),
                'filename' => $filename,
                'file_path' => $filepath,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        Notification::make()
            ->title('Export Complete')
            ->body("Exported " . count($data) . " products to {$filename}")
            ->success()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download')
                    ->url(asset('storage/' . $filename))
                    ->openUrlInNewTab(),
            ])
            ->persistent()
            ->send();
    }
}
