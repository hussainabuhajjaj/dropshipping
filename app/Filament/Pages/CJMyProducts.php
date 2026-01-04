<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Products\Services\CjProductImportService;
use App\Infrastructure\Fulfillment\Clients\CJDropshippingClient;
use App\Models\SiteSetting;
use App\Services\Api\ApiException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class CJMyProducts extends Page implements HasTable
{
    // Livewire property for imported products count (read from cache)
    public int $importedCount = 0;

    // Key for tracking sync job status in cache
    protected string $syncStatusCacheKey = 'cj_my_products_sync_status';

    // Livewire property for UI
    public string $syncStatusLabel = 'Idle';

    public function getImportedCount(): int
    {
        return \Illuminate\Support\Facades\Cache::get('cj_my_products_imported_count', 0);
    }

    public function updated(): void
    {
        $this->importedCount = $this->getImportedCount();
    }

    // Get the current sync status from cache
    public function getSyncStatus(): string
    {
        return \Illuminate\Support\Facades\Cache::get($this->syncStatusCacheKey, 'Idle');
    }

    // Set the sync status in cache
    public function setSyncStatus(string $status): void
    {
        \Illuminate\Support\Facades\Cache::put($this->syncStatusCacheKey, $status, now()->addMinutes(30));
    }
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 92;
    protected string $view = 'filament.pages.cj-my-products';

    public ?array $products = null;
    public int $pageNum = 1;
    public int $pageSize = 20;
    public int $total = 0;
    public int $totalPages = 1;
    public ?string $lastSyncAt = null;
    public ?string $lastSyncSummary = null;
    public ?string $shipToCountry = null;

    // Admin-visible: last CJ API error details (temporary debug aid)
    public ?string $lastApiErrorMessage = null;
    public mixed $lastApiErrorBody = null;
    public ?int $lastApiErrorStatus = null;
    public ?string $lastApiErrorCode = null;
    public ?string $lastApiErrorHint = null; // Human-friendly remediation suggestion for admins

    public function mount(): void
    {
        $default = strtoupper((string) (config('services.cj.ship_to_default') ?? ''));
        $this->shipToCountry = $default !== '' ? $default : null;
        $this->fetch();
        $this->loadSyncInfo();
    }

    public function fetch(): void
    {
        try {
            $this->pageNum = max(1, $this->pageNum);
            $this->pageSize = max(10, $this->pageSize);

            $resp = app(CJDropshippingClient::class)->listMyProducts([
                'pageNum' => $this->pageNum,
                'pageSize' => $this->pageSize,
            ]);
            $this->products = $resp->data ?? null;
            // Clear previous API error details on a successful fetch
            $this->lastApiErrorMessage = null;
            $this->lastApiErrorBody = null;
            $this->lastApiErrorStatus = null;
            $this->lastApiErrorCode = null;

            $this->hydrateTotals();
            Notification::make()->title('Loaded My Products')->success()->send();
            $this->loadSyncInfo();
        } catch (ApiException $e) {
            Log::error('CJ API exception in fetch()', [
                'message' => $e->getMessage(),
                'status' => $e->status,
                'code' => $e->codeString,
                'body' => $e->body,
            ]);

            // Save sanitized error details for admin-only UI debug
            $this->lastApiErrorMessage = $e->getMessage();
            $this->lastApiErrorStatus = $e->status;
            $this->lastApiErrorCode = $e->codeString;
            $body = $e->body;
            if (is_array($body) || is_object($body)) {
                $this->lastApiErrorBody = $body;
            } else {
                $str = (string) $body;
                $this->lastApiErrorBody = strlen($str) > 2000 ? substr($str, 0, 2000) . '... (truncated)' : $str;
            }

            // Compute and set an admin hint for remediation
            $this->lastApiErrorHint = \App\Infrastructure\Fulfillment\Clients\CJ\CjErrorMapper::hint($this->lastApiErrorCode, $this->lastApiErrorStatus, $e->body);

            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Log::error('CJ unexpected exception in fetch()', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function importProduct(string $pid): void
    {
        $pid = trim($pid);
        if ($pid === '') {
            Notification::make()->title('Invalid PID')->warning()->send();
            return;
        }

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
            $this->loadSyncInfo();
        } catch (ApiException $e) {
            Log::error('CJ API exception in importProduct()', [
                'message' => $e->getMessage(),
                'status' => $e->status,
                'code' => $e->codeString,
                'body' => $e->body,
                'pid' => $pid,
            ]);

            // Save sanitized error details for admin UI
            $this->lastApiErrorMessage = $e->getMessage();
            $this->lastApiErrorStatus = $e->status;
            $this->lastApiErrorCode = $e->codeString;
            $body = $e->body;
            if (is_array($body) || is_object($body)) {
                $this->lastApiErrorBody = $body;
            } else {
                $str = (string) $body;
                $this->lastApiErrorBody = strlen($str) > 2000 ? substr($str, 0, 2000) . '... (truncated)' : $str;
            }

            // Compute and set an admin hint for remediation
            $this->lastApiErrorHint = \App\Infrastructure\Fulfillment\Clients\CJ\CjErrorMapper::hint($this->lastApiErrorCode, $this->lastApiErrorStatus, $e->body);

            Notification::make()->title('CJ error')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Log::error('CJ unexpected exception in importProduct()', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function testConnection(): void
    {
        try {
            $resp = app(CJDropshippingClient::class)->listMyProducts([
                'pageNum' => 1,
                'pageSize' => 1,
            ]);

            // Clear previous API error details on a successful test
            $this->lastApiErrorMessage = null;
            $this->lastApiErrorBody = null;
            $this->lastApiErrorStatus = null;
            $this->lastApiErrorCode = null;
            $this->lastApiErrorHint = null;

            $count = is_array($resp->data['content'] ?? null) ? count($resp->data['content']) : null;

            Notification::make()
                ->title('CJ connection OK')
                ->body($count !== null ? "Fetched {$count} item(s)." : 'Connected successfully.')
                ->success()
                ->send();

            // Refresh the in-page data so the user sees updated results if desired
            $this->fetch();
        } catch (ApiException $e) {
            Log::error('CJ API exception in testConnection()', [
                'message' => $e->getMessage(),
                'status' => $e->status,
                'code' => $e->codeString,
                'body' => $e->body,
            ]);

            $this->lastApiErrorMessage = $e->getMessage();
            $this->lastApiErrorStatus = $e->status;
            $this->lastApiErrorCode = $e->codeString;
            $body = $e->body;
            if (is_array($body) || is_object($body)) {
                $this->lastApiErrorBody = $body;
            } else {
                $str = (string) $body;
                $this->lastApiErrorBody = strlen($str) > 2000 ? substr($str, 0, 2000) . '... (truncated)' : $str;
            }

            $this->lastApiErrorHint = $this->mapCJError($this->lastApiErrorCode, $this->lastApiErrorStatus, $e->body);

            Notification::make()
                ->title('CJ test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Log::error('CJ unexpected exception in testConnection()', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    private function hydrateTotals(): void
    {
        $data = $this->products ?? [];
        $this->total = is_numeric($data['totalRecords'] ?? null) ? (int) $data['totalRecords'] : 0;
        $this->totalPages = is_numeric($data['totalPages'] ?? null) ? (int) $data['totalPages'] : 1;
    }

    private function loadSyncInfo(): void
    {
        $settings = SiteSetting::query()->first();
        $this->lastSyncAt = $settings?->cj_last_sync_at?->toDateTimeString();
        $this->lastSyncSummary = $settings?->cj_last_sync_summary;
        $this->syncStatusLabel = $this->getSyncStatus();
    }

    protected function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->productList())
            ->headerActions([
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
                        $this->dispatch('$refresh');
                    }),
                Action::make('clearShipTo')
                    ->label('Clear Ship-to')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->action(function (): void {
                        $this->shipToCountry = null;
                        $this->dispatch('$refresh');
                    }),
            ])
            ->columns([
                ViewColumn::make('image')
                    ->label('Image')
                    ->view('filament.tables.columns.cj-product-image-modal')
                    ->getStateUsing(fn (array $record): array => [
                        'url' => $record['image'] ?? null,
                        'raw' => $record['raw'] ?? [],
                    ]),
                TextColumn::make('name')
                    ->label('Product')
                    ->getStateUsing(fn (array $record): string => $record['name'])
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                TextColumn::make('pid')
                    ->label('PID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->getStateUsing(fn (array $record): string => $record['pid'] ?? ''),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(fn (array $record): ?string => $record['sku'] ?? null)
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Category')
                    ->getStateUsing(fn (array $record): ?string => $record['category'])
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable()
                    ->getStateUsing(fn (array $record): ?float => $record['price']),
                TextColumn::make('delivery_summary')
                    ->label('Delivery')
                    ->toggleable()
                    ->wrap()
                    ->getStateUsing(fn (array $record): ?string => $record['delivery_summary']),
                TextColumn::make('warehouses')
                    ->label('Warehouses')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap()
                    ->getStateUsing(fn (array $record): ?string => $record['warehouses']),
                BadgeColumn::make('listed')
                    ->label('Inventory')
                    ->colors([
                        'success' => static fn (?int $state): bool => $state !== null && $state > 0,
                        'danger' => static fn (?int $state): bool => $state !== null && $state <= 0,
                        'gray' => static fn (?int $state): bool => $state === null,
                    ])
                    ->getStateUsing(fn (array $record): ?int => $record['listed'])
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('refresh')
                    ->label('Refresh list')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (): void {
                        $this->fetch();
                    }),
                Action::make('testConnection')
                    ->label('Test CJ connection')
                    ->icon('heroicon-o-link')
                    ->color('secondary')
                    ->action(function (): void {
                        $this->testConnection();
                    }),
                Action::make('import')
                    ->label('Import by PID')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->schema([
                        TextInput::make('pid')->label('CJ PID')->required()->maxLength(200),
                    ])
                    ->action(function (array $data): void {
                        $this->importProduct(trim((string) ($data['pid'] ?? '')));
                    }),
                Action::make('sync')
                    ->label('Sync catalog now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (): void {
                        $this->setSyncStatus('Queued');
                        \Illuminate\Support\Facades\Artisan::call('cj:sync-my-products-job', [
                            '--start-page' => 1,
                            '--page-size' => 50,
                            '--max-pages' => 10, // adjust as needed
                        ]);
                        $this->setSyncStatus('Running');
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('CJ My Products sync jobs dispatched')
                            ->body('Sync jobs have been queued. Check status below.')
                            ->send();
                        $this->loadSyncInfo();
                    }),
            ])
            ->recordActions([
                Action::make('import')
                    ->label('Import')
                    ->color('success')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->action(function (array $record): void {
                        $this->importProduct((string) ($record['pid'] ?? ''));
                    })
                    ->requiresConfirmation()
                    ->visible(fn (?array $record): bool => is_array($record) && ! empty($record['pid'])), 
                Action::make('view')
                    ->label('View on CJ')
                    ->icon('heroicon-o-eye')
                    ->url(fn (array $record): ?string => $this->recordUrl($record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No CJ products loaded')
            ->emptyStateDescription('Use Refresh list to pull items from your CJ account.');
    }

    private function productList(): array
    {
        $content = $this->products['content'] ?? [];
        if (! is_array($content)) {
            return [];
        }

        // Pre-import ship-to filtering using best-effort warehouse inference
        $shipTo = $this->shipToCountry;
        if ($shipTo) {
            $content = array_values(array_filter($content, function ($item) use ($shipTo): bool {
                if (! is_array($item)) {
                    return true;
                }
                $codes = $this->rawWarehouseCountries($item);
                return $codes === [] || in_array($shipTo, $codes, true);
            }));
        }

        return array_map(fn ($item) => [
            'pid' => (string) ($item['productId'] ?? $item['id'] ?? ''),
            'name' => $item['nameEn'] ?? $item['name'] ?? 'CJ product',
            'sku' => $item['sku'] ?? null,
            'category' => $item['categoryName'] ?? $item['categoryNameEn'] ?? null,
            'price' => isset($item['sellPrice']) && is_numeric($item['sellPrice']) ? (float) $item['sellPrice'] : null,
            'listed' => isset($item['listedShopNum']) && is_numeric($item['listedShopNum']) ? (int) $item['listedShopNum'] : null,
            'image' => $item['bigImg'] ?? $item['productImage'] ?? $item['productImg'] ?? $item['bigImage'] ?? null,
            'delivery_summary' => $this->buildDeliverySummary($item),
            'warehouses' => $this->buildWarehouseList($item),
            'raw' => $item,
        ], $content);
    }

    private function rawWarehouseCountries(array $item): array
    {
        $candidates = [];

        $lists = [
            $item['warehouseList'] ?? null,
            $item['warehouseInfo'] ?? null,
            $item['warehouse'] ?? null,
            $item['warehouses'] ?? null,
        ];

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            foreach ($list as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $code = $entry['countryCode']
                    ?? $entry['country']
                    ?? $entry['warehouseCountryCode']
                    ?? $entry['warehouseCountry']
                    ?? null;

                if (is_string($code) && $code !== '') {
                    $candidates[] = strtoupper($code);
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function recordUrl(array $record): ?string
    {
        $pid = $record['pid'] ?? '';
        return $pid !== '' ? "https://www.cjdropshipping.com/product/{$pid}" : null;
    }

    private function buildDeliverySummary(array $item): ?string
    {
        $logistics = Arr::wrap($item['logisticsList'] ?? $item['logistics'] ?? $item['logisticList'] ?? []);
        $entry = Arr::first($logistics);
        if (! $entry) {
            return null;
        }

        $parts = [];
        if (! empty($entry['logisticsName'])) {
            $parts[] = $entry['logisticsName'];
        }

        if (! empty($entry['countryCode'])) {
            $parts[] = strtoupper($entry['countryCode']);
        }

        if (! empty($entry['freight'])) {
            $currency = $entry['currency'] ?? $entry['currencyCode'] ?? 'USD';
            $parts[] = trim("{$currency} {$entry['freight']}");
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    private function buildWarehouseList(array $item): ?string
    {
        $warehouses = Arr::wrap($item['warehouseList'] ?? $item['warehouseInfo'] ?? $item['warehouse'] ?? []);
        if (empty($warehouses)) {
            return null;
        }

        $names = [];
        foreach ($warehouses as $warehouse) {
            $name = $warehouse['warehouseName'] ?? $warehouse['name'] ?? $warehouse['warehouse'] ?? null;
            if ($name) {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        return $names ? implode(', ', $names) : null;
    }

}
