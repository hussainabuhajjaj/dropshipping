<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\CjProductMediaService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Jobs\SyncCjProductsJob;
use App\Services\Api\ApiException;
use BackedEnum;
// use Filament\Tables\Actions\Action;
use Carbon\Carbon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Notifications\Notification;
use App\Filament\Pages\BasePage;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use UnitEnum;

class CJCatalog extends BasePage implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 91;
    protected string $view = 'filament.pages.cj-catalog';

    public ?array $products = null;
    public array $items = [];
    public array $existingCatalog = [];
    public int $pageNum = 1;
    public int $pageSize = 24;
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
    public int $syncEnabledCount = 0;
    public int $syncDisabledCount = 0;
    public int $syncStaleCount = 0;
    public ?string $lastCommandMessage = null;
    public ?string $lastCommandAt = null;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection): array => $this->buildTableRecords($search, $sortColumn, $sortDirection))
            ->headerActions([
                Action::make('syncListedCjProducts')
                    ->label('Sync Listed CJ Products')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (): void {
                        try {
                            $client = app(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class);
                            $resp = $client->listMyProducts([
                                'pageNum' => 1,
                                'pageSize' => 100,
                            ]);
                            $data = $resp->data ?? [];
                            // dd($data);
                            $list = [];
                            // Normalize response to array of products
                            if (is_array($data)) {
                                if (!empty($data['content']) && is_array($data['content'])) {
                                    foreach ($data['content'] as $entry) {
                                        if (is_array($entry) && isset($entry['productList']) && is_array($entry['productList'])) {
                                            $list = array_merge($list, $entry['productList']);
                                        } elseif (is_array($entry)) {
                                            $list[] = $entry;
                                        }
                                    }
                                } elseif (!empty($data['productList']) && is_array($data['productList'])) {
                                    $list = $data['productList'];
                                } elseif (!empty($data['content']) && is_array($data['content'])) {
                                    $list = $data['content'];
                                } else {
                                    $numericKeys = array_filter(array_keys($data), 'is_int');
                                    if ($numericKeys !== []) {
                                        $list = $data;
                                    }
                                }
                            }
                            // Filter for listed products only
                            $listed = array_filter($list, function ($item) {
                                return is_array($item) && !empty($item['listedShopNum']) && (int)$item['listedShopNum'] > 0;
                            });
                            $importer = app(\App\Domain\Products\Services\CjProductImportService::class);
                            $count = 0;
                            foreach ($listed as $record) {
                                $pid = $record['pid'] ?? $record['productId'] ?? $record['id'] ?? null;
                                if (!$pid) {
                                    continue;
                                }
                                try {
                                    $product = $importer->importByPid($pid, [
                                        'respectSyncFlag' => false,
                                        'defaultSyncEnabled' => true,
                                        'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                                    ]);
                                } catch (\Throwable $e) {
                                    \Filament\Notifications\Notification::make()->title('CJ error')->body("{$pid}: {$e->getMessage()}")->danger()->send();
                                    continue;
                                }
                                if ($product) {
                                    $count++;
                                }
                            }
                            $message = $count > 0 ? "Imported {$count} listed CJ products." : 'No listed CJ products were imported.';
                            \Filament\Notifications\Notification::make()->title('Listed Products')->body($message)->success()->send();
                            $this->recordCommandMessage($message);
                            $this->fetch();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('setShipTo')
                    ->label('Ship-to Filter')
                    ->icon('heroicon-o-globe-alt')
                    ->color('secondary')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('country')
                            ->label('Country code (e.g., US, GB)')
                            ->maxLength(2)
                            ->default(strtoupper((string) (config('services.cj.ship_to_default') ?? ''))),
                    ])
                    ->action(function (array $data): void {
                        $code = strtoupper(trim((string) ($data['country'] ?? '')));
                        $this->shipToCountry = $code !== '' ? $code : null;
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
            ])
            ->columns([
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
            ])
            ->recordActions([
                Action::make('import')
                    ->label('Import')
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
            ])
            ->striped()
            ->paginated(false)
            ->defaultKeySort(false)
            ->emptyStateHeading('No CJ products found')
            ->emptyStateDescription('Adjust your filters or refresh the catalog.');
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
        $default = strtoupper((string) (config('services.cj.ship_to_default') ?? ''));
        $this->shipToCountry = $default !== '' ? $default : null;
        $this->loadCategories();
        $this->loadWarehouses();
        $this->fetch();
    }

    private function loadCategories(): void
    {
        try {
            $resp = app(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class)->listCategories();
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

    public function fetch(bool $append = false, bool $notify = true): void
    {
        try {
            $this->pageNum = max(1, $this->pageNum);
            $this->pageSize = min(200, max(10, $this->pageSize));

            $sort = in_array($this->sort, ['1', '2', '5', '6'], true) ? $this->sort : null;

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

            $client = app(CJDropshippingClient::class);
            $resp = $this->storeProductId
                ? $client->listMyProducts($filters)
                : $client->listProducts($filters);
            $this->products = $resp->data ?? null;
            $this->hydrateResults($append);
            if ($notify) {
                Notification::make()
                    ->title($append ? 'Loaded more CJ products' : 'Loaded CJ catalog')
                    ->success()
                    ->send();
            }
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->icon('heroicon-o-exclamation-circle')->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function import(string $pid): void
    {
        $importer = app(CjProductImportService::class);

        try {
            $product = $importer->importByPid($pid, [
                'respectSyncFlag' => false,
                'defaultSyncEnabled' => true,
                'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
            ]);

            if (! $product) {
                Notification::make()->title('CJ product not found')->danger()->send();
                return;
            }

            Notification::make()->title("Imported {$product->name}")->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function addToMyProducts(string $pid): void
    {
        try {
            app(CJDropshippingClient::class)->addToMyProducts($pid);
            Notification::make()->title('Added to My Products')->success()->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function checkStock(string $pid): void
    {
        try {
            $resp = app(CJDropshippingClient::class)->getStockByPid($pid);
            $total = $this->sumStorage($resp->data ?? null);
            Notification::make()->title('CJ stock')->body("PID {$pid}: {$total}")->send();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
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
            $client = app(CJDropshippingClient::class);
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
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
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
        $this->pageNum = max(1, $this->pageNum);
        $this->pageSize = min(200, max(10, $this->pageSize));
        $this->fetch();
    }

    public function resetFilters(): void
    {
        $this->productName = null;
        $this->productSku = null;
        $this->materialKey = null;
        $this->categoryId = null;
        $this->warehouseId = null;
        $this->inStockOnly = false;
        $this->sort = '';
        $this->storeProductId = null;
        $this->pageNum = 1;
        $this->pageSize = 24;
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
        $importer = app(CjProductImportService::class);
        $imported = 0;
        $batch = array_slice($this->items, 0, 15);

        foreach ($batch as $record) {
            $pid = $this->recordPid($record);
            if ($pid === '') {
                continue;
            }

            try {
                $product = $importer->importByPid($pid, [
                    'respectSyncFlag' => false,
                    'defaultSyncEnabled' => true,
                    'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                ]);
            } catch (ApiException $e) {
                Notification::make()->title('CJ error')->body("{$pid}: {$e->getMessage()}")->danger()->send();
                continue;
            } catch (\Throwable $e) {
                Notification::make()->title('Error')->body("{$pid}: {$e->getMessage()}")->danger()->send();
                continue;
            }

            if ($product) {
                $imported++;
            }
        }

        $message = $imported > 0 ? "Imported {$imported} products from this page." : 'No new products were imported.';
        Notification::make()->title('Bulk import')->body($message)->success()->send();
        $this->recordCommandMessage($message);
        $this->fetch();
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
            $client = app(CJDropshippingClient::class);
            $resp = $client->listMyProducts([
                'pageNum' => 1,
                'pageSize' => 50,
            ]);

            [, $list] = $this->resolveCatalogPayload((array) ($resp->data ?? []));
            $importer = app(CjProductImportService::class);
            $count = 0;

            foreach ($list as $record) {
                $pid = $this->recordPid($record);
                if ($pid === '') {
                    continue;
                }

                try {
                    $product = $importer->importByPid($pid, [
                        'respectSyncFlag' => false,
                        'defaultSyncEnabled' => true,
                        'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                    ]);
                } catch (ApiException $e) {
                    Notification::make()->title('CJ error')->body("{$pid}: {$e->getMessage()}")->danger()->send();
                    continue;
                }

                if ($product) {
                    $count++;
                }
            }

            $message = $count > 0 ? "Imported {$count} of your CJ My Products." : 'No CJ My Products were imported.';
            Notification::make()->title('My Products')->body($message)->success()->send();
            $this->recordCommandMessage($message);
            $this->fetch();
        } catch (ApiException $e) {
            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
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
        $pageSizeValue = $this->extractNumeric($content, $payload, ['pageSize', 'size', 'limit']) ?? $this->pageSize ?? 24;

        $this->pageNum = max(1, $pageNumValue);
        $this->pageSize = max(10, $pageSizeValue);

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
                $record['__key'] = $pid !== '' ? $pid : md5(json_encode($record, JSON_UNESCAPED_SLASHES));
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
        if (is_array($record) && isset($record['__key'])) {
            return (string) $record['__key'];
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
        return $record['categoryName'] ?? $record['categoryNameEn'] ?? null;
    }

    private function recordPrice(array $record): ?float
    {
        return is_numeric($record['sellPrice'] ?? null)
            ? (float) $record['sellPrice']
            : (is_numeric($record['productSellPrice'] ?? null) ? (float) $record['productSellPrice'] : null);
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
}
