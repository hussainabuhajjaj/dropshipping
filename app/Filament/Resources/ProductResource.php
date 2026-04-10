<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Models\Category;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\ProductActivationValidator;
use App\Domain\Products\Services\PricingService;
use App\Services\Admin\AdminCurrencyService;
use App\Filament\Resources\ProductResource\Pages;
use App\Jobs\ApplyProductMarginChunkJob;
use App\Models\Product;
use App\Jobs\TranslateProductJob;
use App\Jobs\TranslateProductsChunkJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use App\Filament\Resources\BaseResource;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TagsColumn;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Services\ProductMarginLogger;

class ProductResource extends BaseResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 10;

    private const CJ_SYNC_STALE_HOURS = 24;
    private const BULK_MARGIN_QUEUE_THRESHOLD = 150;
    private const BULK_MARGIN_QUEUE_CHUNK = 200;
    // Livewire property for imported products count (read from cache)
    public static function getImportedCount(): int
    {
        return \Illuminate\Support\Facades\Cache::get('cj_my_products_imported_count', 0);
    }

    // Key for tracking global product sync job status in cache
    protected static string $globalSyncStatusCacheKey = 'product_global_sync_status';

    // Get the current global sync status from cache
    public static function getGlobalSyncStatus(): string
    {
        return \Illuminate\Support\Facades\Cache::get(self::$globalSyncStatusCacheKey, 'Idle');
    }

    // Set the global sync status in cache
    public static function setGlobalSyncStatus(string $status): void
    {
        \Illuminate\Support\Facades\Cache::put(self::$globalSyncStatusCacheKey, $status, now()->addMinutes(30));
    }
    protected static ?string $model = Product::class;



    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withQualityScore(self::CJ_SYNC_STALE_HOURS)
            ->addSelect([
                'orders_count' => DB::table('order_items')
                    ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('COUNT(DISTINCT order_items.order_id)'),
                'units_sold' => DB::table('order_items')
                    ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(order_items.quantity), 0)'),
                'revenue_total' => DB::table('order_items')
                    ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->selectRaw('COALESCE(SUM(order_items.total), 0)'),
            ])
            ->withCount([
                'images',
                'variants',
                'reviews',
                'variants as variants_without_price_count' => function (Builder $query): void {
                    $query->where(function (Builder $inner): void {
                        $inner->whereNull('price')
                            ->orWhere('price', '<=', 0);
                    });
                },
            ])
            ->with(['images', 'latestMarginLog', 'localWarehouse']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Basics')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('category_id')
                        ->label('Category')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload(),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->helperText('URL slug')
                        ->dehydrateStateUsing(fn ($state, callable $get) => $state ?: Str::slug($get('name'))),
                    Textarea::make('description')->rows(3),
                    Toggle::make('is_active')->label('Active')->default(true),
                    Toggle::make('is_featured')->label('Featured'),
                ])->columns(2),
            Section::make('SEO')
                ->schema([
                    TextInput::make('meta_title')
                        ->label('Meta title')
                        ->maxLength(255),
                    Textarea::make('meta_description')
                        ->label('Meta description')
                        ->rows(3),
                ])->columns(2),
            Section::make('Pricing')
                ->schema([
                    Placeholder::make('pricing_guide')
                        ->label('Pricing hierarchy')
                        ->content(
                            htmlspecialchars('<div class="text-sm text-slate-600 space-y-1">'
                                . '<p><strong>Product price:</strong> default for all variants.</p>'
                                . '<p><strong>Variant price:</strong> overrides product price when set.</p>'
                                . '<p><strong>Margin:</strong> calculated from cost; must meet minimum threshold.</p>'
                                . '</div>')
                        ),
                    TextInput::make('selling_price')
                        ->label('Selling Price (USD)')
                        ->helperText('Default product-level price in USD; variants can override.')
                        ->prefix('$')
                        ->numeric()
                        ->step(0.01)
                        ->required()
                        ->rules(function (callable $get) {
                            return [
                                function (string $attribute, $value, callable $fail) use ($get) {
                                    $cost = (float) $get('cost_price');
                                    $selling = (float) $value;
                                    $pricing = PricingService::makeFromConfig();
                                    try {
                                        $pricing->validatePrice($cost, $selling);
                                    } catch (\InvalidArgumentException $e) {
                                        $fail($e->getMessage());
                                    }
                                },
                            ];
                        }),
                    TextInput::make('cost_price')
                        ->label('Cost Price (USD)')
                        ->helperText('Baseline cost in USD; used when variant cost is missing.')
                        ->prefix('$')
                        ->numeric()
                        ->step(0.01)
                        ->required()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            $warning = self::marginWarning($get('selling_price'), $get('cost_price'));
                            $set('margin_warning', $warning);
                        }),
                    Placeholder::make('margin_warning')
                        ->label('Margin warning')
                        ->content(fn (callable $get) => self::marginWarning($get('selling_price'), $get('cost_price')))
                        ->visible(fn (callable $get) => self::marginWarning($get('selling_price'), $get('cost_price')) !== null)
                        ->extraAttributes(['class' => 'text-sm text-amber-600']),
                ])->columns(3),
            Section::make('Inventory')
                ->schema([
                    TextInput::make('stock_on_hand')
                        ->label('Stock on hand')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Used for local products and warehouse stock.'),
                ])
                ->columns(1)
                ->visible(fn ($record) => blank($record?->cj_pid)),
            Section::make('Suppliers & Fulfillment')
                ->schema([
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(fn () => FulfillmentProvider::query()->where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Select::make('default_fulfillment_provider_id')
                        ->label('Default Fulfillment Provider')
                        ->options(fn () => FulfillmentProvider::query()->where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    TextInput::make('supplier_product_url')
                        ->label('Supplier product URL')
                        ->url(),
                    TextInput::make('shipping_estimate_days')
                        ->label('Ship estimate (days)')
                        ->numeric()
                        ->minValue(0),
                ])->columns(2),
            Section::make('CJ Sync')
                ->schema([
                    TextInput::make('cj_pid')
                        ->label('CJ PID')
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('cj_warehouse_id')
                        ->label('CJ Warehouse')
                        ->options(fn () => app(\App\Domain\Fulfillment\Services\CJWarehouseService::class)->getWarehouseOptions())
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $warehouses = app(\App\Domain\Fulfillment\Services\CJWarehouseService::class)->getWarehouseOptions();
                            $set('cj_warehouse_name', $warehouses[$state] ?? null);
                        })
                        ->helperText('Select the CJ warehouse for this product.')
                        ->columnSpan(1),
                    TextInput::make('cj_warehouse_name')
                        ->label('Warehouse Name')
                        ->disabled()
                        ->dehydrated()
                        ->columnSpan(1),
                    Toggle::make('cj_sync_enabled')
                        ->label('Sync from CJ')
                        ->helperText('Allow CJ to update this product during automatic sync.'),
                    Toggle::make('cj_lock_price')
                        ->label('Lock price')
                        ->helperText('Prevent CJ from updating pricing.'),
                    Toggle::make('cj_lock_description')
                        ->label('Lock description')
                        ->helperText('Prevent CJ from updating description.'),
                    Toggle::make('cj_lock_images')
                        ->label('Lock images')
                        ->helperText('Prevent CJ from updating images.'),
                    Toggle::make('cj_lock_variants')
                        ->label('Lock variants')
                        ->helperText('Prevent CJ from updating variants.'),
                ])
                ->columns(2)
                ->visible(fn ($record) => filled($record?->cj_pid)),
            Section::make('CJ Payload Details')
                ->schema([
                    Placeholder::make('cj_payload_product_type')
                        ->label('Product type')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'productType'))),
                    Placeholder::make('cj_payload_supplier_name')
                        ->label('Supplier name')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'supplierName'))),
                    Placeholder::make('cj_payload_discount_price')
                        ->label('Discount price')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'discountPrice'))),
                    Placeholder::make('cj_payload_discount_rate')
                        ->label('Discount rate')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'discountPriceRate'))),
                    Placeholder::make('cj_payload_add_mark_status')
                        ->label('Add mark status')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'addMarkStatus'))),
                    Placeholder::make('cj_payload_is_video')
                        ->label('Is video')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'isVideo'))),
                    Placeholder::make('cj_payload_warehouse_inventory_num')
                        ->label('Warehouse inventory')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'warehouseInventoryNum'))),
                    Placeholder::make('cj_payload_total_verified_inventory')
                        ->label('Total verified inventory')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'totalVerifiedInventory'))),
                    Placeholder::make('cj_payload_total_unverified_inventory')
                        ->label('Total unverified inventory')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'totalUnVerifiedInventory'))),
                    Placeholder::make('cj_payload_delivery_cycle')
                        ->label('Delivery cycle')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'deliveryCycle'))),
                    Placeholder::make('cj_payload_video_list')
                        ->label('Video list')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'videoList')))
                        ->columnSpanFull(),
                    Placeholder::make('cj_payload_verified_warehouses')
                        ->label('Verified warehouses')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'verifiedWarehouses')))
                        ->columnSpanFull(),
                    Placeholder::make('cj_payload_my_product')
                        ->label('My product')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'myProduct')))
                        ->columnSpanFull(),
                    Placeholder::make('cj_payload_inventory_info')
                        ->label('Inventory info')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'inventoryInfo')))
                        ->columnSpanFull(),
                    Placeholder::make('cj_payload_variant_inventories')
                        ->label('Variant inventories')
                        ->content(fn ($record) => self::formatCjPayloadValue(self::cjVariantInventories($record)))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible()
                ->visible(fn ($record) => $record === null || filled($record?->cj_pid)),
            Section::make('CJ Audit')
                ->schema([
                    Placeholder::make('cj_synced_at')
                        ->label('Last synced')
                        ->content(fn ($record) => $record?->cj_synced_at?->toDateTimeString() ?? 'Never'),
                    Placeholder::make('cj_last_changed_fields')
                        ->label('Last changed fields')
                        ->content(function ($record) {
                            $fields = $record?->cj_last_changed_fields ?? [];
                            return $fields ? implode(', ', $fields) : '--';
                        }),
                    Textarea::make('cj_last_payload')
                        ->label('Last CJ payload')
                        ->rows(8)
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($state, $record) => $record?->cj_last_payload
                            ? json_encode($record->cj_last_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : '--'),
                ])
                ->columns(2)
                ->collapsible()
                ->visible(fn ($record) => filled($record?->cj_pid)),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primary_image')
                    ->label('Image')
                    ->getStateUsing(fn (Product $record) => $record->images->sortBy('position')->first()?->url)
                    ->square()
                    ->checkFileExistence(false)
                    ->action(
                        Action::make('viewImages')
                            ->modalHeading(fn (?Product $record) => $record?->name ?? 'Product Images')
                            ->modalContent(fn (?Product $record) => view('filament.modals.product-images', [
                                'images' => $record?->images?->sortBy('position')->pluck('url')->filter()->values()->all() ?? [],
                            ]))
                            ->modalWidth('4xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                    ),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->limit(20,'...')->tooltip(fn ($record) => $record->name),
                Tables\Columns\TextColumn::make('quality_score')
                    ->label('Quality')
                    ->numeric(decimalPlaces: 0)
                    ->badge()
                    ->color(fn (int|float|string|null $state): string => match (true) {
                        (float) $state < 40 => 'danger',
                        (float) $state < 70 => 'warning',
                        default => 'success',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cj_pid')->searchable()->sortable()->limit(10),
                    Tables\Columns\TextColumn::make('source')
                        ->label('Source')
                        ->getStateUsing(fn (Product $record) => self::sourceLabel($record))
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'CJ' => 'info',
                            'AliExpress' => 'warning',
                            default => 'gray',
                        })
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('sync_status')
                        ->label('Sync')
                        ->getStateUsing(fn (Product $record) => self::syncStatus($record))
                        ->badge()
                        ->color(fn (Product $record) => self::syncStatusColor($record))
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('cj_availability')
                        ->label('CJ Availability')
                        ->getStateUsing(fn (Product $record) => self::cjAvailability($record))
                        ->badge()
                        ->color(fn (Product $record) => self::cjAvailabilityColor($record))
                        ->tooltip(fn (Product $record) => $record->cj_removed_reason ?: null)
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('translation_status')
                        ->label('Translation')
                        ->badge()
                        ->getStateUsing(fn (Product $record) => $record->translation_status ?? 'not translated')
                        ->color(fn (?string $state) => match ($state) {
                            'completed' => 'success',
                            'in_progress' => 'warning',
                            'failed' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (?string $state) => Str::headline($state ?? 'not translated'))
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('cj_pid')
                        ->label('CJ PID')
                        ->copyable()
                        ->searchable()
                        ->tooltip(fn ($state) => $state)
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable()->toggleable(),
                    Tables\Columns\IconColumn::make('is_active')->boolean(),
                    Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured')->toggleable(),
                    Tables\Columns\TextColumn::make('selling_price')
                        ->label('Selling Price')
                        ->formatStateUsing(fn (float $state, Product $record): string => AdminCurrencyService::formatPrice($state, $record->currency))
                        ->sortable(),
                    Tables\Columns\TextColumn::make('cost_price')
                        ->label('Cost Price')
                        ->formatStateUsing(fn (float $state, Product $record): string => AdminCurrencyService::formatCost($state, $record->currency))
                        ->sortable(),
                    Tables\Columns\TextColumn::make('pricing_engine')
                        ->label('Pricing Engine')
                        ->state(fn (Product $record): string => data_get($record->pricing_meta, 'margin_source') === 'weight_based' ? 'Weight Based' : 'Legacy')
                        ->badge()
                        ->color(fn (Product $record): string => data_get($record->pricing_meta, 'margin_source') === 'weight_based' ? 'success' : 'gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('pricing_meta.margin_used')
                        ->label('Margin Used')
                        ->state(function (Product $record): string {
                            $margin = data_get($record->pricing_meta, 'margin_used');

                            return is_numeric($margin) ? number_format(((float) $margin) * 100, 2) . '%' : '--';
                        })
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('pricing_meta.landed_cost')
                        ->label('Landed Cost')
                        ->state(function (Product $record): string {
                            $landedCost = data_get($record->pricing_meta, 'landed_cost');

                            return is_numeric($landedCost)
                                ? AdminCurrencyService::formatCost((float) $landedCost, $record->currency)
                                : '--';
                        })
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('latestMarginLog.old_selling_price')
                        ->label('Old Price')
                        ->formatStateUsing(fn (float $state): string => AdminCurrencyService::formatPrice($state))
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('latestMarginLog.new_selling_price')
                        ->label('New Price')
                        ->formatStateUsing(fn (float $state): string => AdminCurrencyService::formatPrice($state))
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('latestMarginLog.created_at')
                        ->label('Margin Updated At')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\BadgeColumn::make('margin_status')
                        ->label('Margin Status')
                        ->getStateUsing(function ($record) {
                            return self::validateProductMargin($record)['status'];
                        })
                        ->colors([
                            'danger' => 'Missing',
                            'warning' => 'Below Required',
                            'success' => 'OK',
                        ]),
                    Tables\Columns\TextColumn::make('stock_on_hand')
                        ->label('Stock')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                          Tables\Columns\TextColumn::make('variants.compare_at_price')
                        ->label('Compare At Price')
                        ->formatStateUsing(fn (float $state): string => AdminCurrencyService::formatPrice($state))
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('images_count')
                        ->label('Images')
                        ->badge()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('variants_count')
                        ->label('Variants')
                        ->badge()
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('variants_without_price_count')
                        ->label('Variants No Price')
                        ->badge()
                        ->color(fn (int|string|null $state): string => ((int) $state) > 0 ? 'danger' : 'success')
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('reviews_count')
                        ->label('Reviews')
                        ->badge()
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('orders_count')
                        ->label('Orders')
                        ->badge()
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->sortable()->toggleable(),
                    Tables\Columns\TextColumn::make('defaultFulfillmentProvider.name')
                        ->label('Fulfillment')
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('shipping_estimate_days')
                        ->label('Ship est. (d)')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('cj_synced_at')
                        ->label('Last synced')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('cj_imported_at')
                        ->label('CJ Imported')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->color(fn ($record) => $record && $record->cj_imported_at && $record->cj_imported_at->isToday() ? 'success' : null)
                        ->description(fn ($record) => $record && $record->cj_imported_at ? $record->cj_imported_at->diffForHumans() : null),
                    Tables\Columns\TextColumn::make('cj_import_batch_id')
                        ->label('Import Batch')
                        ->copyable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->visible(fn ($record) => $record && filled($record->cj_import_batch_id)),
                    TagsColumn::make('cj_last_changed_fields')
                        ->label('Recent changes')
                        ->getStateUsing(fn (Product $record) => is_array($record->cj_last_changed_fields) ? $record->cj_last_changed_fields : [])
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->tooltip(fn (Product $record) => is_array($record->cj_last_changed_fields) ? implode(', ', $record->cj_last_changed_fields) : null),
                    Tables\Columns\TextColumn::make('cj_last_payload')
                        ->label('CJ Payload')
                        ->getStateUsing(fn (Product $record): ?string =>
                            $record->cj_last_payload ?
                            self::extractCjPayloadSummary($record->cj_last_payload) : ''
                        )
                        ->limit(50)
                        ->tooltip(fn (Product $record) => 'Click to view full CJ payload data')
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('media_status')
                        ->label('Media status')
                        ->getStateUsing(fn (Product $record) => self::mediaStatus($record))
                        ->badge(fn (Product $record) => self::mediaStatusColor($record))
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\IconColumn::make('cj_lock_price')
                        ->label('Price lock')
                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\IconColumn::make('cj_lock_description')
                        ->label('Description lock')
                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\IconColumn::make('cj_lock_images')
                        ->label('Images lock')
                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\IconColumn::make('cj_lock_variants')
                        ->label('Variants lock')
                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                ])
                ->paginated()
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('ali_express')
                    ->label('AliExpress')
                    ->query(function ($query) {
                        return $query->where(function ($inner) {
                            $inner->whereNotNull('attributes->ali_item_id')
                                ->orWhere('attributes->supplier_code', 'ae');
                        });
                    })
                    ->toggle(),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(function (): array {
                        $categories = Category::query()->orderBy('name')->pluck('name', 'id')->all();

                        // Add option for uncategorized products at the top
                        return ['null' => 'Uncategorized'] + $categories;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'];

                        // Explicitly handle empty/null values
                        if (blank($value)) {
                            return $query;
                        }

                        // Handle uncategorized products
                        if ($value === 'null') {
                            return $query->whereNull('category_id');
                        }

                        return $query->where('category_id', $value);
                    })
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('cj_removed')
                    ->label('Removed from CJ')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('cj_removed_from_shelves_at'))
                    ->toggle(),
                Tables\Filters\Filter::make('out_of_sync')
                    ->label('Out of Sync')
                    ->query(function ($query) {
                        $cutoff = now()->subHours(self::CJ_SYNC_STALE_HOURS);
                        return $query->where(function ($inner) use ($cutoff) {
                            $inner->where('cj_pid', '!=', '')
                                ->where('cj_sync_enabled', true)
                                ->where(function ($sync) use ($cutoff) {
                                    $sync->whereNull('cj_synced_at')
                                        ->orWhere('cj_synced_at', '<', $cutoff);
                                });
                            });
                    })
                    ->toggle(),
                Tables\Filters\Filter::make('missing_images')
                    ->label('Missing Images')
                    ->query(fn ($query) => $query->doesntHave('images'))
                    ->toggle(),
                Tables\Filters\Filter::make('selling_price_zero')
                    ->label('Selling Price = 0')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function (Builder $inner): void {
                            $inner->whereNull('selling_price')
                                ->orWhere('selling_price', '=', 0);
                        });
                    })
                    ->toggle(),
                Tables\Filters\Filter::make('variants_without_price')
                    ->label('Variants Missing Price')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('variants', function (Builder $variants): void {
                            $variants->where(function (Builder $price): void {
                                $price->whereNull('price')
                                    ->orWhere('price', '=', 0);
                            });
                        });
                    })
                    ->toggle(),
                Tables\Filters\Filter::make('untranslated')
                    ->label('Untranslated')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function (Builder $inner): void {
                            $inner->where('translation_status', '!=', 'completed')
                                ->orWhereNull('translation_status')
                                ->orWhereNotExists(function ($subquery) {
                                    $subquery->selectRaw('1')
                                        ->from('product_translations as pt')
                                        ->whereColumn('pt.product_id', 'products.id');
                                });
                        });
                    })
                    ->toggle(),
                Tables\Filters\Filter::make('low_quality')
                    ->label('Low Quality (<= 60)')
                    ->query(function (Builder $query): Builder {
                        // Apply the quality score scope first
                        return $query->withQualityScore()
                            ->having('quality_score', '<=', 60);
                    })
                    ->toggle(),
                Tables\Filters\SelectFilter::make('sync_flag')
                    ->label('Sync')
                    ->options([
                        'enabled' => 'Enabled',
                        'disabled' => 'Disabled',
                        'all' => 'All',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $flag = $data['value'] ?? 'all';
                        if ($flag === 'enabled') {
                            return $query->where('cj_sync_enabled', true);
                        } elseif ($flag === 'disabled') {
                            return $query->where(function (Builder $inner): void {
                                $inner->where('cj_sync_enabled', false)
                                    ->orWhereNull('cj_pid');
                            });
                        }
                        return $query;
                    })
                    ->default('all')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('updated_at_range')
                    ->label('Updated Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if ($from && $until) {
                            $query->whereBetween('updated_at', [$from, $until]);
                        } elseif ($from) {
                            $query->where('updated_at', '>=', $from);
                        } elseif ($until) {
                            $query->where('updated_at', '<=', $until);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): string {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if (!$from && !$until) {
                            return '';
                        }

                        if ($from && $until) {
                            return "Updated: {$from} - {$until}";
                        } elseif ($from) {
                            return "Updated from: {$from}";
                        } else {
                            return "Updated until: {$until}";
                        }
                    }),
                Tables\Filters\Filter::make('margin_not_set')
                    ->label('Margin Not Set')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function (Builder $inner): void {
                            $inner->where(function ($subQuery): void {
                                // Case 1: Missing cost or selling price (equivalent to 'Missing' status)
                                $subQuery->whereNull('cost_price')
                                    ->orWhereNull('selling_price')
                                    ->orWhere('cost_price', '<', 0)
                                    ->orWhere('selling_price', '<', 0);
                            })
                            ->orWhere(function ($subQuery): void {
                                // Case 2: Below required margin (equivalent to 'Below Required' status)
                                $subQuery->whereNotNull('cost_price')
                                    ->whereNotNull('selling_price')
                                    ->where('cost_price', '>', 0)
                                    ->where('selling_price', '>', 0)
                                    ->whereRaw('selling_price < (cost_price * 1.50)');
                            });
                        });
                    })
                    ->toggle(),

                // CJ Import Filters
                Tables\Filters\Filter::make('cj_imported')
                    ->label('CJ Imported')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('cj_imported_at'))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('cj_import_timeframe')
                    ->label('CJ Import Timeframe')
                    ->options([
                        'today' => 'Today',
                        'this_week' => 'This Week',
                        'this_month' => 'This Month',
                        'last_24h' => 'Last 24 Hours',
                        'last_7d' => 'Last 7 Days',
                        'last_30d' => 'Last 30 Days',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'today' => $query->whereDate('cj_imported_at', today()),
                            'this_week' => $query->whereBetween('cj_imported_at', [now()->startOfWeek(), now()->endOfWeek()]),
                            'this_month' => $query->whereMonth('cj_imported_at', now()->month)->whereYear('cj_imported_at', now()->year),
                            'last_24h' => $query->where('cj_imported_at', '>=', now()->subHours(24)),
                            'last_7d' => $query->where('cj_imported_at', '>=', now()->subDays(7)),
                            'last_30d' => $query->where('cj_imported_at', '>=', now()->subDays(30)),
                            default => $query,
                        };
                    })
                    ->default('all')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('cj_import_date_range')
                    ->label('CJ Import Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if ($from && $until) {
                            $query->whereBetween('cj_imported_at', [$from, $until]);
                        } elseif ($from) {
                            $query->where('cj_imported_at', '>=', $from);
                        } elseif ($until) {
                            $query->where('cj_imported_at', '<=', $until);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): string {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if (!$from && !$until) {
                            return '';
                        }

                        if ($from && $until) {
                            return "CJ Imported: {$from} - {$until}";
                        } elseif ($from) {
                            return "CJ Imported: From {$from}";
                        } else {
                            return "CJ Imported: Until {$until}";
                        }
                    }),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ActionsEditAction::make(),
                    Action::make('quickEdit')
                        ->label('Quick edit')
                        ->icon('heroicon-o-pencil-square')
                        ->slideOver()
                        ->schema([
                            TextInput::make('selling_price')->label('Selling Price (USD)')
                                ->helperText('Default product-level price in USD; variants may override.')
                                ->numeric()->required()
                                ->prefix('$')
                                ->step(0.01),
                            TextInput::make('cost_price')->label('Cost Price (USD)')->numeric()->required()->prefix('$')->step(0.01),
                            TextInput::make('stock_on_hand')->label('Stock on hand')->numeric()->minValue(200)->default(200),
                            Toggle::make('is_active')->label('Active'),
                            Toggle::make('is_featured')->label('Featured'),

                            // CJ Payload Section
                            Section::make('CJ API Payload')
                                ->description('Raw data from the last CJ Dropshipping API sync')
                                ->schema([
                                    Placeholder::make('cj_payload_summary')
                                        ->label('Payload Summary')
                                        ->content(fn (Product $record): string =>
                                            $record->cj_last_payload ?
                                            self::extractCjPayloadSummary($record->cj_last_payload) :
                                            'No CJ payload data available'
                                        ),
                                    Textarea::make('cj_payload_raw')
                                        ->label('Full CJ Payload (JSON)')
                                        ->formatStateUsing(fn (Product $record): string =>
                                            $record->cj_last_payload ?
                                            json_encode($record->cj_last_payload['sellPrice'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) :
                                            ''
                                        )
                                        ->rows(8)
                                        ->disabled()
                                        ->helperText('Complete raw payload data from CJ API for debugging purposes'),
                                ])
                                ->collapsible()
                                ->collapsed(fn (Product $record) => !$record->cj_last_payload),
                        ])
                        ->fillForm(fn (Product $record) => [
                            'selling_price' => $record->selling_price,
                            'cost_price' => $record->cost_price,
                            'stock_on_hand' => $record->stock_on_hand,
                            'is_active' => (bool) $record->is_active,
                            'is_featured' => (bool) $record->is_featured,
                        ])
                        ->action(function (Product $record, array $data): void {
                            $record->update([
                                'selling_price' => $data['selling_price'],
                                'cost_price' => $data['cost_price'],
                                'stock_on_hand' => $data['stock_on_hand'] ?? null,
                                'is_active' => (bool) ($data['is_active'] ?? $record->is_active),
                                'is_featured' => (bool) ($data['is_featured'] ?? $record->is_featured),
                            ]);
                        }),
                    Action::make('downloadImages')
                        ->label('Download images')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->modalHeading(fn (Product $record) => 'Download images: ' . $record->name)
                        ->modalSubmitActionLabel('Download selected')
                        ->schema([
                            \Filament\Forms\Components\ViewField::make('image_ids')
                                ->label('')
                                ->default(fn (Product $record) => $record->images()->pluck('id')->map(fn ($id) => (string) $id)->all())
                                ->view('filament.forms.product-image-selector')
                                ->viewData(fn (Product $record) => [
                                    'images' => $record
                                        ->images()
                                        ->orderBy('position')
                                        ->get(['id', 'url', 'position'])
                                        ->map(fn ($img) => [
                                            'id' => $img->id,
                                            'url' => $img->url,
                                            'position' => $img->position,
                                        ])
                                        ->all(),
                                ]),
                        ])
                        ->action(function (Product $record, array $data) {
                            $ids = collect($data['image_ids'] ?? [])
                                ->map(fn ($id) => (int) trim((string) $id))
                                ->filter(fn (int $id) => $id > 0)
                                ->unique()
                                ->values()
                                ->all();

                            if ($ids === []) {
                                Notification::make()
                                    ->title('No images selected')
                                    ->warning()
                                    ->send();
                                return null;
                            }

                            $url = URL::temporarySignedRoute(
                                'admin.exports.product-images',
                                now()->addMinutes(5),
                                [
                                    'product' => $record->id,
                                    'ids' => implode(',', $ids),
                                ]
                            );

                            return redirect()->to($url);
                        }),
                    Action::make('setMargin')
                        ->label(fn (): string => config('pricing.use_new_engine') ? 'Recalculate pricing' : 'Set margin')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Placeholder::make('pricing_mode_notice')
                                ->label('Pricing mode')
                                ->content(config('pricing.use_new_engine')
                                    ? 'Weight-based pricing is active. This action will recalculate the product and variant prices from cost, weight, shipping, and warehouse data.'
                                    : 'Legacy manual margin pricing is active.'),
                            TextInput::make('margin_percent')
                                ->label('Margin %')
                                ->numeric()
                                ->default(50)
                                ->minValue(0)
                                ->maxValue(500)
                                ->required()
                                ->visible(fn (): bool => ! config('pricing.use_new_engine')),
                            Toggle::make('apply_to_variants')
                                ->label('Apply to variants')
                                ->default(true),
                            Toggle::make('queue_compare_at')
                                ->label('Queue compare-at refresh')
                                ->default(true),
                            Toggle::make('activate_if_valid')
                                ->label('Activate if valid')
                                ->default(true),
                        ])
                        ->action(function (Product $record, array $data): void {
                            try {
                                if (config('pricing.use_new_engine')) {
                                    $result = self::repriceProductWithCurrentEngine($record, [
                                        'apply_to_variants' => (bool) ($data['apply_to_variants'] ?? true),
                                        'activate_if_valid' => (bool) ($data['activate_if_valid'] ?? true),
                                        'reason' => 'Manual dynamic repricing via admin panel',
                                    ]);

                                    Notification::make()
                                        ->title($result['success'] ? 'Pricing updated' : 'Failed to update pricing')
                                        ->body($result['message'])
                                        ->color($result['success'] ? 'success' : 'danger')
                                        ->send();

                                    return;
                                }

                                $margin = (float) ($data['margin_percent'] ?? 0);
                                if ($margin < 0) {
                                    Notification::make()
                                        ->title('Invalid margin')
                                        ->body('Margin must be greater than or equal to 0.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                // Use centralized margin setting with validation
                                $options = [
                                    'apply_to_variants' => (bool) ($data['apply_to_variants'] ?? true),
                                    'queue_compare_at' => (bool) ($data['queue_compare_at'] ?? true),
                                    'activate_if_valid' => (bool) ($data['activate_if_valid'] ?? true),
                                    'reason' => 'Manual margin adjustment via admin panel'
                                ];

                                $result = self::setProductMargin($record, $margin, $options);

                                if ($result['success']) {
                                    // Apply to variants if requested
                                    if ($options['apply_to_variants']) {
                                        $record->loadMissing('variants');
                                        foreach ($record->variants as $variant) {
                                            $variant->setRelation('product', $record);
                                            $variantCost = self::normalizeAmount($variant->cost_price);
                                            if ($variantCost === null || $variantCost < 0) {
                                                continue;
                                            }

                                            $oldVariantPrice = self::normalizeAmount($variant->price);
                                            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                                            $minVariantPrice = $pricing->minSellingPrice($variantCost);
                                            $calculatedVariantPrice = $variantCost * (1 + $margin / 100);
                                            $newVariantPrice = max($calculatedVariantPrice, $minVariantPrice);
                                            $newVariantPrice = round($newVariantPrice, 2);

                                            $variant->update(['price' => $newVariantPrice]);

                                            // Log variant margin change
                                            app(\App\Services\ProductMarginLogger::class)->logVariant($variant, [
                                                'event' => 'variant_manual_set',
                                                'source' => 'manual',
                                                'old_selling_price' => $oldVariantPrice,
                                                'new_selling_price' => $newVariantPrice,
                                                'notes' => 'Variant margin adjustment via admin panel',
                                            ]);
                                        }
                                    }

                                    // Queue compare-at price refresh if requested
                                    if ($options['queue_compare_at']) {
                                        // Implementation depends on your compare-at refresh system
                                    }

                                    $message = $result['message'];
                                    if (!empty($result['warnings'])) {
                                        $message .= '. Warnings: ' . implode(', ', $result['warnings']);
                                    }

                                    Notification::make()
                                        ->title('Margin set successfully')
                                        ->body($message)
                                        ->success()
                                        ->send();

                                } else {
                                    Notification::make()
                                        ->title('Failed to set margin')
                                        ->body($result['message'])
                                        ->danger()
                                        ->send();
                                }

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error setting margin')
                                    ->body('An unexpected error occurred: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('syncMedia')
                        ->label('Sync media')
                        ->icon('heroicon-o-photo')
                        ->requiresConfirmation()
                        ->visible(fn (Product $record) => filled($record->cj_pid))
                        ->action(function (Product $record): void {
                            $importer = app(CjProductImportService::class);

                            try {
                                $updated = $importer->syncMedia($record, [
                                    'respectSyncFlag' => false,
                                    'respectLocks' => true,
                                ]);
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Error')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                                return;
                            }

                            if (! $updated) {
                                Notification::make()
                                    ->title('Media not updated')
                                    ->body('Unlock images or confirm CJ media exists before retrying.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            Notification::make()
                                ->title('Media synced')
                                ->success()
                                ->send();
                        }),
                    Action::make('importReviews')
                        ->label('Import reviews')
                        ->icon('heroicon-o-star')
                        ->requiresConfirmation()
                        ->visible(fn (Product $record) => filled($record->cj_pid))
                        ->action(function (Product $record): void {
                            $importer = app(CjProductImportService::class);

                            try {
                                $result = $importer->syncReviews($record, [
                                    'throwOnFailure' => true,
                                ]);
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Error importing reviews')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                                return;
                            }

                            Notification::make()
                                ->title('Reviews imported')
                                ->body("Fetched {$result['fetched']} | Created {$result['created']} | Updated {$result['updated']}")
                                ->success()
                                ->send();
                        }),
                    Action::make('translate')
                        ->label('Translate')
                        ->icon('heroicon-o-language')
                        ->requiresConfirmation()
                        ->action(function (Product $record): void {
                            if (empty(config('services.deepseek.key'))) {
                                Notification::make()
                                    ->title('DeepSeek not configured')
                                    ->body('Set DEEPSEEK_API_KEY in your .env to enable AI features.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            TranslateProductJob::dispatch((int) $record->id, ['en', 'fr'], 'en', false)
                                ->onQueue('translations');

                            Notification::make()
                                ->title('Translation queued')
                                ->success()
                                ->send();
                        }),
                    Action::make('generateSeo')
                        ->label('Generate SEO')
                        ->icon('heroicon-o-sparkles')
                        ->requiresConfirmation()
                        ->action(function (Product $record): void {
                            if (empty(config('services.deepseek.key'))) {
                                Notification::make()
                                    ->title('DeepSeek not configured')
                                    ->body('Set DEEPSEEK_API_KEY in your .env to enable AI features.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \App\Jobs\GenerateProductSeoJob::dispatch((int) $record->id, 'en', true);

                            Notification::make()
                                ->title('SEO queued')
                                ->success()
                                ->send();
                        }),
                    Action::make('generateMarketing')
                        ->label('Generate Marketing')
                        ->icon('heroicon-o-megaphone')
                        ->requiresConfirmation()
                        ->action(function (Product $record): void {
                            if (empty(config('services.deepseek.key'))) {
                                Notification::make()
                                    ->title('DeepSeek not configured')
                                    ->body('Set DEEPSEEK_API_KEY in your .env to enable AI features.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \App\Jobs\GenerateProductMarketingJob::dispatch((int) $record->id, 'en', true, 'friendly');

                            Notification::make()
                                ->title('Marketing queued')
                                ->success()
                                ->send();
                        }),
                    Action::make('generateCompareAt')
                        ->label('Generate Compare-at')
                        ->icon('heroicon-o-receipt-percent')
                        ->requiresConfirmation()
                        ->schema([
                            Toggle::make('force')
                                ->label('Force overwrite')
                                ->default(false),
                            Toggle::make('run_now')
                                ->label('Run now (no queue)')
                                ->default(false),
                        ])
                        ->action(function (Product $record, array $data): void {
                            $force = (bool) ($data['force'] ?? false);
                            $runNow = (bool) ($data['run_now'] ?? false);

                            if ($runNow) {
                                try {
                                    app(\App\Services\Pricing\ProductCompareAtService::class)->generate($record, $force);
                                    Notification::make()
                                        ->title('Compare-at updated')
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('Compare-at failed')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                                return;
                            }

                            \App\Jobs\GenerateProductCompareAtJob::dispatch((int) $record->id, $force);

                            Notification::make()
                                ->title('Compare-at queued')
                                ->success()
                                ->send();
                        }),
                    Action::make('preview')
                        ->label('Preview')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Product $record) => route('products.show', $record->slug))
                        ->openUrlInNewTab(),
                    Action::make('toggleActive')
                        ->label('Activate/Deactivate')
                        ->icon('heroicon-o-power')
                        ->action(function (Product $record): void {
                            $newActive = ! $record->is_active;

                            if (! $newActive) {
                                $record->update(['is_active' => false]);
                                return;
                            }

                            $validator = app(ProductActivationValidator::class);
                            $errors = $validator->errorsForActivation($record->loadMissing('images', 'variants'));

                            if ($errors !== []) {
                                Notification::make()
                                    ->title('Cannot activate product')
                                    ->body(implode(' ', $errors))
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $record->update([
                                'is_active' => true,
                                'status' => 'active',
                            ]);
                        }),
                ])

            ])
            ->toolbarActions([
                Action::make('cjChunkedImport')
                    ->label('CJ Chunked Import')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->form([
                        TextInput::make('page')->label('Start page')->default(1),
                        TextInput::make('size')->label('CJ page size')->default(50),
                        TextInput::make('chunk')->label('Chunk size')->default(25),
                        TextInput::make('limit')->label('Limit (0 = none)')->default(0),
                        TextInput::make('category')->label('Category ID')->default(''),
                    ])
                    ->action(function (array $data): void {
                        $params = [
                            '--page' => (int) ($data['page'] ?? 1),
                            '--size' => (int) ($data['size'] ?? 50),
                            '--chunk' => (int) ($data['chunk'] ?? 25),
                        ];
                        $limit = (int) ($data['limit'] ?? 0);
                        if ($limit > 0) {
                            $params['--limit'] = $limit;
                        }
                        $category = trim((string) ($data['category'] ?? ''));
                        if ($category !== '') {
                            $params['--category'] = $category;
                        }

                        try {
                            \App\Jobs\RunCjSyncCommandJob::dispatch($params)->onQueue('cj-import');
                            Notification::make()->title('Chunked import queued')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Import enqueue failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                ActionsBulkActionGroup::make([
                    BulkAction::make('enableSync')
                        ->label('Enable Sync')
                        ->icon('heroicon-o-play')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $targets = $records->filter(fn (Product $record) => (bool) $record->cj_pid);
                            $targets->each->update(['cj_sync_enabled' => true]);

                            Notification::make()
                                ->title('Sync enabled')
                                ->body("Enabled sync for {$targets->count()} product(s).")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('disableSync')
                        ->label('Disable Sync')
                        ->icon('heroicon-o-pause')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $targets = $records->filter(fn (Product $record) => (bool) $record->cj_pid);
                            $targets->each->update(['cj_sync_enabled' => false]);

                            Notification::make()
                                ->title('Sync disabled')
                                ->body("Disabled sync for {$targets->count()} product(s).")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('syncNow')
                        ->label('Sync Now')
                        ->icon('heroicon-o-arrow-path')
                        ->schema([
                            Toggle::make('sync_variants')
                                ->label('Sync variants')
                                ->default(true),
                            Toggle::make('import_reviews')
                                ->label('Import reviews')
                                ->default(true),
                            TextInput::make('review_score')
                                ->label('Review score filter')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(5)
                                ->helperText('Optional. Leave empty to import all review scores.'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            $importer = app(CjProductImportService::class);
                            $synced = 0;
                            $skipped = 0;
                            $errors = 0;
                            $syncVariants = (bool) ($data['sync_variants'] ?? true);
                            $importReviews = (bool) ($data['import_reviews'] ?? true);
                            $reviewScore = $data['review_score'] ?? null;
                            $reviewScore = is_numeric($reviewScore) ? max(1, min(5, (int) $reviewScore)) : null;

                            foreach ($records as $record) {
                                if (! $record->cj_pid) {
                                    $skipped++;
                                    continue;
                                }

                                if (! $record->cj_sync_enabled) {
                                    $skipped++;
                                    continue;
                                }

                                try {
                                    $product = $importer->importByPid($record->cj_pid, [
                                        'respectSyncFlag' => true,
                                        'defaultSyncEnabled' => true,
                                        'respectLocks' => false, // ensure variant/options/attrs refresh
                                        'syncVariants' => $syncVariants,
                                        'syncReviews' => $importReviews,
                                        'reviewScore' => $reviewScore,
                                        'reviewMaxPages' => 10,
                                        'shipToCountry' => (string) (config('services.cj.ship_to_default') ?? ''),
                                    ]);
                                } catch (\Throwable) {
                                    $errors++;
                                    continue;
                                }

                                if ($product) {
                                    $synced++;
                                }
                            }

                            Notification::make()
                                ->title('CJ sync complete')
                                ->body("Synced {$synced} product(s), skipped {$skipped}, errors {$errors}.")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('importReviews')
                        ->label('Import reviews')
                        ->icon('heroicon-o-star')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $importer = app(CjProductImportService::class);

                            $targets = $records->filter(fn (Product $record) => (bool) $record->cj_pid);
                            $created = 0;
                            $updated = 0;
                            $fetched = 0;
                            $errors = 0;

                            foreach ($targets as $record) {
                                try {
                                    $result = $importer->syncReviews($record, [
                                        'throwOnFailure' => true,
                                    ]);
                                } catch (\Throwable) {
                                    $errors++;
                                    continue;
                                }

                                $created += (int) ($result['created'] ?? 0);
                                $updated += (int) ($result['updated'] ?? 0);
                                $fetched += (int) ($result['fetched'] ?? 0);
                            }

                            Notification::make()
                                ->title('Review import complete')
                                ->body("Products {$targets->count()} | Fetched {$fetched} | Created {$created} | Updated {$updated} | Errors {$errors}")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('syncMedia')
                        ->label('Sync Media')
                        ->icon('heroicon-o-photo')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $ids = $records
                                ->filter(fn (Product $r) => filled($r->cj_pid))
                                ->pluck('id')
                                ->map(fn ($id) => (int) $id)
                                ->values()
                                ->all();

                            if (empty($ids)) {
                                Notification::make()->title('No CJ products selected')->warning()->send();
                                return;
                            }

                            $chunkSize = 20;
                            $jobCount = 0;
                            foreach (array_chunk($ids, $chunkSize) as $chunk) {
                                \App\Jobs\SyncProductMediaChunkJob::dispatch($chunk)->onQueue('media');
                                $jobCount++;
                            }

                            Notification::make()
                                ->title('Media sync queued')
                                ->body("Dispatched {$jobCount} job(s) for " . count($ids) . ' product(s) on the [media] queue.')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('syncVariants')
                        ->label('Sync Variants')
                        ->icon('heroicon-o-squares-2x2')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $ids = $records
                                ->filter(fn (Product $r) => filled($r->cj_pid))
                                ->pluck('id')
                                ->map(fn ($id) => (int) $id)
                                ->values()
                                ->all();

                            if (empty($ids)) {
                                Notification::make()->title('No CJ products selected')->warning()->send();
                                return;
                            }

                            $chunkSize = 20;
                            $jobCount = 0;
                            foreach (array_chunk($ids, $chunkSize) as $chunk) {
                                \App\Jobs\SyncProductVariantsChunkJob::dispatch($chunk)->onQueue('variants');
                                $jobCount++;
                            }

                            Notification::make()
                                ->title('Variants sync queued')
                                ->body("Dispatched {$jobCount} job(s) for " . count($ids) . ' product(s) on the [variants] queue.')
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('chunkedSyncSelected')
                        ->label('Chunked Sync Selected')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->schema([
                            TextInput::make('chunk_size')->label('Chunk size')->default(25),
                            Toggle::make('sync_media')->label('Dispatch media sync')->default(true),
                            Toggle::make('sync_variants')->label('Dispatch variants sync')->default(true),
                            Toggle::make('force')->label('Force (ignore sync flag)')->default(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $pids = $records->pluck('cj_pid')->filter()->unique()->values()->all();
                            if (empty($pids)) {
                                Notification::make()->title('No CJ PIDs selected')->warning()->send();
                                return;
                            }

                            $chunkSize = max(1, (int) ($data['chunk_size'] ?? 25));
                            $client = app(\App\Infrastructure\Fulfillment\Clients\CJDropshippingClient::class);
                            $claimService = app(\App\Services\Admin\AdminCurrencyService::class);

                            foreach (array_chunk($pids, $chunkSize) as $pidChunk) {
                                $payloads = [];
                                foreach ($pidChunk as $pid) {
                                    $rec = $records->first(fn($r) => $r->cj_pid === $pid);
                                    $payload = $rec?->getAttribute('attributes')['cj_payload'] ?? $rec?->cj_last_payload ?? null;

                                    if (! is_array($payload) || $payload === []) {
                                        try {
                                            $resp = $client->getProduct($pid);
                                            $payload = $resp->data ?? null;
                                        } catch (\Throwable) {
                                            $payload = null;
                                        }
                                    }

                                    if (! is_array($payload) || $payload === []) {
                                        continue;
                                    }

                                    try {
                                        $token = $claimService->claim((string)$pid);
                                        $payload['_cj_claim_token'] = $token;
                                    } catch (\Throwable) {
                                        // ignore claim failures here
                                    }

                                    $payload['id'] = $pid;
                                    $payloads[] = $payload;
                                }

                                if ($payloads !== []) {
                                    \App\Jobs\ImportCjProductChunkJob::dispatch($payloads)->onQueue('cj-import');
                                }
                            }

                            Notification::make()->title('Chunked sync queued')->success()->send();
                        }),
                    BulkAction::make('setMargin')
                        ->label(fn (): string => config('pricing.use_new_engine') ? 'Recalculate pricing' : 'Set Margin %')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Placeholder::make('pricing_mode_notice')
                                ->label('Pricing mode')
                                ->content(config('pricing.use_new_engine')
                                    ? 'Weight-based pricing is active. Selected products will be repriced using centralized pricing rules.'
                                    : 'Legacy manual margin pricing is active.'),
                            Select::make('margin_preset')
                                ->label('Margin preset')
                                ->options([
                                    '20' => '20%',
                                    '25' => '25%',
                                    '30' => '30%',
                                    '35' => '35%',
                                    '40' => '40%',
                                    '50' => '50%',
                                    'custom' => 'Custom',
                                ])
                                ->default('35')
                                ->native(false)
                                ->required()
                                ->reactive()
                                ->visible(fn (): bool => ! config('pricing.use_new_engine')),
                            TextInput::make('margin_percent')
                                ->label('Custom margin %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(500)
                                ->step('0.01')
                                ->required()
                                ->visible(fn (callable $get): bool => ! config('pricing.use_new_engine') && $get('margin_preset') === 'custom')
                                ->reactive(),
                            Toggle::make('apply_to_variants')
                                ->label('Apply to variants')
                                ->default(true),
                            Toggle::make('use_low_cost_rule')
                                ->label('Use special margin for low-cost products')
                                ->default(true)
                                ->visible(fn (): bool => ! config('pricing.use_new_engine'))
                                ->reactive(),
                            TextInput::make('low_cost_min')
                                ->label('Low-cost min ($)')
                                ->numeric()
                                ->default(0.01)
                                ->minValue(0)
                                ->step('0.01')
                                ->visible(fn (callable $get): bool => ! config('pricing.use_new_engine') && (bool) $get('use_low_cost_rule'))
                                ->reactive(),
                            TextInput::make('low_cost_max')
                                ->label('Low-cost max ($)')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.01)
                                ->step('0.01')
                                ->visible(fn (callable $get): bool => ! config('pricing.use_new_engine') && (bool) $get('use_low_cost_rule'))
                                ->reactive(),
                            TextInput::make('low_cost_margin_percent')
                                ->label('Low-cost margin %')
                                ->numeric()
                                ->default(300)
                                ->minValue(0)
                                ->maxValue(2000)
                                ->step('0.01')
                                ->visible(fn (callable $get): bool => ! config('pricing.use_new_engine') && (bool) $get('use_low_cost_rule'))
                                ->reactive(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            if (config('pricing.use_new_engine')) {
                                $updated = 0;
                                $failed = 0;

                                $records->loadMissing('variants', 'localWarehouse');

                                foreach ($records as $record) {
                                    $result = self::repriceProductWithCurrentEngine($record, [
                                        'apply_to_variants' => (bool) ($data['apply_to_variants'] ?? true),
                                        'activate_if_valid' => true,
                                        'reason' => 'Bulk dynamic repricing via admin panel',
                                    ]);

                                    if ($result['success']) {
                                        $updated++;
                                    } else {
                                        $failed++;
                                    }
                                }

                                Notification::make()
                                    ->title('Dynamic repricing complete')
                                    ->body("Updated {$updated} product(s)." . ($failed > 0 ? " {$failed} product(s) failed." : ''))
                                    ->color($failed > 0 ? 'warning' : 'success')
                                    ->send();
                                return;
                            }

                            $preset = (string) ($data['margin_preset'] ?? '35');
                            $margin = $preset === 'custom'
                                ? (float) ($data['margin_percent'] ?? 0)
                                : (float) $preset;

                            if ($margin < 0) {
                                Notification::make()
                                    ->title('Invalid margin')
                                    ->body('Margin must be greater than or equal to 0.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $applyVariants = (bool) ($data['apply_to_variants'] ?? true);
                            $useLowCostRule = (bool) ($data['use_low_cost_rule'] ?? true);
                            $lowCostMin = is_numeric($data['low_cost_min'] ?? null) ? (float) $data['low_cost_min'] : 0.01;
                            $lowCostMax = is_numeric($data['low_cost_max'] ?? null) ? (float) $data['low_cost_max'] : 1.0;
                            $lowCostMargin = is_numeric($data['low_cost_margin_percent'] ?? null) ? (float) $data['low_cost_margin_percent'] : 300.0;

                            if ($useLowCostRule && $lowCostMax < $lowCostMin) {
                                Notification::make()
                                    ->title('Invalid low-cost range')
                                    ->body('Low-cost max must be greater than or equal to low-cost min.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $selectedIds = $records
                                ->pluck('id')
                                ->filter(fn (mixed $id): bool => is_numeric($id))
                                ->map(fn (mixed $id): int => (int) $id)
                                ->values()
                                ->all();

                            if ($selectedIds === []) {
                                Notification::make()
                                    ->title('No selected products')
                                    ->body('Please select at least one product.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            if (count($selectedIds) > self::BULK_MARGIN_QUEUE_THRESHOLD) {
                                $jobCount = 0;
                                $queueName = (string) config('pricing.bulk_margin_queue', 'pricing');
                                foreach (array_chunk($selectedIds, self::BULK_MARGIN_QUEUE_CHUNK) as $idsChunk) {
                                    ApplyProductMarginChunkJob::dispatch(
                                        productIds: $idsChunk,
                                        margin: $margin,
                                        applyVariants: $applyVariants,
                                        useLowCostRule: $useLowCostRule,
                                        lowCostMin: $lowCostMin,
                                        lowCostMax: $lowCostMax,
                                        lowCostMargin: $lowCostMargin,
                                    )->onQueue($queueName);
                                    $jobCount++;
                                }

                                $message = "Queued " . count($selectedIds) . " product(s) in {$jobCount} job(s) on '{$queueName}' queue. Products without cost price will be skipped automatically.";

                                Notification::make()
                                    ->title('Margin update queued')
                                    ->body($message)
                                    ->success()
                                    ->send();
                                return;
                            }

                            $records->load('variants');

                            $updated = 0;
                            $skipped = 0;
                            $variantUpdated = 0;
                            $variantSkipped = 0;
                            $compareAtQueued = 0;
                            $activationSkipped = 0;
                            $lowCostProductApplied = 0;
                            $lowCostVariantApplied = 0;
                            $logRows = [];
                            $logsInserted = 0;
                            $logger = app(ProductMarginLogger::class);
                            $validator = app(ProductActivationValidator::class);

                            $records->each(function (Product $record) use (
                                $margin,
                                $applyVariants,
                                $useLowCostRule,
                                $lowCostMin,
                                $lowCostMax,
                                $lowCostMargin,
                                &$updated,
                                &$skipped,
                                &$variantUpdated,
                                &$variantSkipped,
                                &$compareAtQueued,
                                &$activationSkipped,
                                &$lowCostProductApplied,
                                &$lowCostVariantApplied,
                                &$logRows,
                                &$logsInserted,
                                $logger,
                                $validator
                            ): void {
                                $productCost = self::normalizeAmount($record->cost_price);
                                if ($productCost === null || $productCost < 0) {
                                    $skipped++;
                                    return;
                                }

                                $oldSelling = $record->selling_price;
                                $oldStatus = $record->status;
                                $oldActive = $record->is_active;
                                $appliedMargin = $margin;
                                if ($useLowCostRule && $productCost >= $lowCostMin && $productCost <= $lowCostMax) {
                                    $appliedMargin = $lowCostMargin;
                                    $lowCostProductApplied++;
                                }

                                // Calculate new selling price with minimum price validation
                                $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                                $minSelling = $pricing->minSellingPrice($productCost);
                                $calculatedPrice = $productCost * (1 + $appliedMargin / 100);
                                $newSelling = max($calculatedPrice, $minSelling);
                                $newSelling = round($newSelling, 2);

                                $record->update([
                                    'selling_price' => $newSelling,
                                ]);
                                $updated++;

                                $logRows[] = $logger->prepareProductRow($record, [
                                    'event' => 'margin_updated',
                                    'source' => 'manual',
                                    'old_selling_price' => $oldSelling,
                                    'new_selling_price' => $newSelling,
                                    'old_status' => $oldStatus,
                                    'new_status' => $record->status,
                                    'notes' => "Margin set to {$appliedMargin}%",
                                    'skip_sales_count' => true,
                                ]);
                                if (count($logRows) >= 500) {
                                    $logsInserted += $logger->insertMany($logRows);
                                    $logRows = [];
                                }

                                $needsCompareAt = false;

                                if ($applyVariants) {
                                    $record->variants->each(function ($variant) use (
                                        $margin,
                                        $useLowCostRule,
                                        $lowCostMin,
                                        $lowCostMax,
                                        $lowCostMargin,
                                        &$variantUpdated,
                                        &$variantSkipped,
                                        &$needsCompareAt,
                                        &$lowCostVariantApplied,
                                        &$logRows,
                                        &$logsInserted,
                                        $logger
                                    ): void {
                                        $variantCost = self::normalizeAmount($variant->cost_price);
                                        if ($variantCost === null || $variantCost < 0) {
                                            $variantSkipped++;
                                            return;
                                        }
                                        $variantMargin = $margin;
                                        if ($useLowCostRule && $variantCost >= $lowCostMin && $variantCost <= $lowCostMax) {
                                            $variantMargin = $lowCostMargin;
                                            $lowCostVariantApplied++;
                                        }
                                        $oldVariantPrice = $variant->price;

                                        // Calculate new variant price with minimum price validation
                                        $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                                        $minVariantPrice = $pricing->minSellingPrice($variantCost);
                                        $calculatedVariantPrice = $variantCost * (1 + $variantMargin / 100);
                                        $newVariantPrice = max($calculatedVariantPrice, $minVariantPrice);
                                        $newVariantPrice = round($newVariantPrice, 2);

                                        $variant->update([
                                            'price' => $newVariantPrice,
                                        ]);
                                        $variantUpdated++;
                                        $logRows[] = $logger->prepareVariantRow($variant, [
                                            'event' => 'variant_margin_updated',
                                            'source' => 'manual',
                                            'old_selling_price' => $oldVariantPrice,
                                            'new_selling_price' => $variant->price,
                                            'notes' => "Margin set to {$variantMargin}% for variant",
                                            'skip_sales_count' => true,
                                        ]);
                                        if (count($logRows) >= 500) {
                                            $logsInserted += $logger->insertMany($logRows);
                                            $logRows = [];
                                        }

                                        if (is_numeric($oldVariantPrice) && is_numeric($variant->compare_at_price)) {
                                            $price = (float) $variant->price;
                                            $oldPrice = (float) $oldVariantPrice;
                                            $compareAt = (float) $variant->compare_at_price;
                                            if ($price > $oldPrice && $compareAt <= $price) {
                                                $needsCompareAt = true;
                                            }
                                        }
                                    });
                                }

                                if ($needsCompareAt) {
                                    \App\Jobs\GenerateProductCompareAtJob::dispatch((int) $record->id, false)
                                        ->onQueue((string) config('pricing.compare_at_queue', config('pricing.bulk_margin_queue', 'pricing')));
                                    $compareAtQueued++;
                                }

                                if (! $oldActive) {
                                    $record->loadMissing('images', 'variants');
                                    $errors = $validator->errorsForActivation($record);
                                    if ($errors === []) {
                                        $record->update([
                                            'is_active' => true,
                                            'status' => 'active',
                                        ]);

                                        $logRows[] = $logger->prepareProductRow($record, [
                                            'event' => 'activated',
                                            'source' => 'manual',
                                            'old_selling_price' => $oldSelling,
                                            'new_selling_price' => $newSelling,
                                            'old_status' => $oldStatus,
                                            'new_status' => 'active',
                                            'notes' => 'Product activated after margin adjustment',
                                            'skip_sales_count' => true,
                                        ]);
                                        if (count($logRows) >= 500) {
                                            $logsInserted += $logger->insertMany($logRows);
                                            $logRows = [];
                                        }
                                    } else {
                                        $activationSkipped++;
                                    }
                                }
                            });

                            if ($logRows !== []) {
                                $logsInserted += $logger->insertMany($logRows);
                            }

                            $body = "Updated $updated product(s) and $variantUpdated variant(s).";
                            if ($skipped > 0 || $variantSkipped > 0) {
                                $body .= " Skipped $skipped product(s) and $variantSkipped variant(s) due to missing or invalid cost price.";
                            }
                            if ($compareAtQueued > 0) {
                                $body .= " Queued compare-at refresh for $compareAtQueued product(s).";
                            }
                            if ($activationSkipped > 0) {
                                $body .= " {$activationSkipped} product(s) were not activated due to activation validation.";
                            }
                            if ($useLowCostRule) {
                                $body .= " Applied low-cost margin to {$lowCostProductApplied} product(s) and {$lowCostVariantApplied} variant(s).";
                            }
                            if ($logsInserted > 0) {
                                $body .= " Logged {$logsInserted} margin event(s).";
                            }

                            Notification::make()
                                ->title('Margin update complete')
                                ->body($body)
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('addToFeatured')
                        ->label('Add to Featured')
                        ->icon('heroicon-o-star')
                        ->action(function (Collection $records): void {
                            $records->each->update(['is_featured' => true]);

                            Notification::make()
                                ->title('Featured updated')
                                ->body("Marked {$records->count()} product(s) as featured.")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-power')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $ids = $records->pluck('id')->filter()->all();
                            if ($ids === []) {
                                return;
                            }

                            $deactivated = Product::query()
                                ->whereKey($ids)
                                ->where('is_active', true)
                                ->update(['is_active' => false]);

                            Notification::make()
                                ->title('Products deactivated')
                                ->body("Deactivated {$deactivated} product(s).")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('translate')
                        ->label('Translate')
                        ->icon('heroicon-o-language')
                        ->schema([
                            TextInput::make('locales')
                                ->label('Locales')
                                ->default('en,fr')
                                ->required(),
                            TextInput::make('source_locale')
                                ->label('Source locale')
                                ->default('en')
                                ->required(),
                            Toggle::make('force')
                                ->label('Force re-translate')
                                ->default(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            if (empty(config('services.deepseek.key'))) {
                                Notification::make()
                                    ->title('DeepSeek not configured')
                                    ->body('Set DEEPSEEK_API_KEY in your .env to enable AI features.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $locales = array_values(array_filter(array_map('trim', explode(',', (string) ($data['locales'] ?? '')))));
                            $source = (string) ($data['source_locale'] ?? 'en');
                            $force = (bool) ($data['force'] ?? false);

                            if ($records->count() > 20) {
                                $productIds = $records->pluck('id')->toArray();
                                $chunkSize = 10;
                                $chunks = array_chunk($productIds, $chunkSize);

                                foreach ($chunks as $chunk) {
                                    TranslateProductsChunkJob::dispatch($chunk, $locales)
                                        ->onConnection('redis')
                                        ->onQueue('translations');
                                }

                                Notification::make()
                                    ->title('Translations queued')
                                    ->body("Queued {$records->count()} product(s) using optimized chunk jobs.")
                                    ->success()
                                    ->send();
                            } else {
                                foreach ($records as $record) {
                                    TranslateProductJob::dispatch((int) $record->id, $locales, $source, $force)
                                        ->onConnection('redis')
                                        ->onQueue('translations');
                                }

                                Notification::make()
                                    ->title('Translations queued')
                                    ->body("Queued {$records->count()} product(s).")
                                    ->success()
                                    ->send();
                            }
                        }),
                    BulkAction::make('generateSeo')
                        ->label('Generate SEO')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Toggle::make('force')
                                ->label('Force overwrite')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            if (empty(config('services.deepseek.key'))) {
                                Notification::make()
                                    ->title('DeepSeek not configured')
                                    ->body('Set DEEPSEEK_API_KEY in your .env to enable AI features.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $force = (bool) ($data['force'] ?? true);

                            foreach ($records as $record) {
                                \App\Jobs\GenerateProductSeoJob::dispatch((int) $record->id, 'en', $force);
                            }

                            Notification::make()
                                ->title('SEO queued')
                                ->body("Queued {$records->count()} product(s).")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('generateCompareAt')
                        ->label('Generate Compare-at')
                        ->icon('heroicon-o-receipt-percent')
                        ->schema([
                            Toggle::make('force')
                                ->label('Force overwrite')
                                ->default(false),
                            Toggle::make('run_now')
                                ->label('Run now (no queue)')
                                ->default(false),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $force = (bool) ($data['force'] ?? false);
                            $runNow = (bool) ($data['run_now'] ?? false);

                            if ($runNow) {
                                $service = app(\App\Services\Pricing\ProductCompareAtService::class);
                                $updated = 0;
                                $failed = 0;

                                foreach ($records as $record) {
                                    try {
                                        $service->generate($record, $force);
                                        $updated++;
                                    } catch (\Throwable $e) {
                                        $failed++;
                                    }
                                }

                                Notification::make()
                                    ->title('Compare-at updated')
                                    ->body("Updated {$updated} product(s). Failed {$failed}.")
                                    ->success()
                                    ->send();
                                return;
                            }

                            foreach ($records as $record) {
                                \App\Jobs\GenerateProductCompareAtJob::dispatch((int) $record->id, $force);
                            }

                            Notification::make()
                                ->title('Compare-at queued')
                                ->body("Queued {$records->count()} product(s).")
                                ->success()
                                ->send();
                        }),
                    ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Product Overview')
                ->schema([
                    TextEntry::make('name')
                        ->label('Name')
                        ->columnSpan(2),
                    TextEntry::make('slug')
                        ->label('Slug')
                        ->copyable(),
                    TextEntry::make('source')
                        ->label('Source')
                        ->state(fn (Product $record) => self::sourceLabel($record))
                        ->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('is_active')
                        ->label('Active')
                        ->state(fn (Product $record) => $record->is_active ? 'Yes' : 'No')
                        ->badge(),
                    TextEntry::make('is_featured')
                        ->label('Featured')
                        ->state(fn (Product $record) => $record->is_featured ? 'Yes' : 'No')
                        ->badge(),
                    TextEntry::make('category.name')
                        ->label('Category')
                        ->state(fn (Product $record) => $record->category?->name ?? '--'),
                    TextEntry::make('supplier.name')
                        ->label('Supplier')
                        ->state(fn (Product $record) => $record->supplier?->name ?? '--'),
                    TextEntry::make('defaultFulfillmentProvider.name')
                        ->label('Fulfillment')
                        ->state(fn (Product $record) => $record->defaultFulfillmentProvider?->name ?? '--'),
                    TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),
                    TextEntry::make('updated_at')
                        ->label('Updated')
                        ->dateTime(),
                    TextEntry::make('description')
                        ->label('Description')
                        ->state(fn (Product $record) => trim(strip_tags((string) ($record->description ?? ''))) ?: '--')
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('Commerce & Quality')
                ->schema([
                    TextEntry::make('selling_price')->label('Selling Price')->money('USD'),
                    TextEntry::make('cost_price')->label('Cost Price')->money('USD'),
                    TextEntry::make('quality_score')
                        ->label('Quality Score')
                        ->badge()
                        ->state(fn (Product $record) => is_numeric($record->quality_score ?? null) ? number_format((float) $record->quality_score, 0) : '0'),
                    TextEntry::make('orders_count')
                        ->label('Orders')
                        ->state(fn (Product $record) => (string) ((int) ($record->orders_count ?? 0)))
                        ->badge(),
                    TextEntry::make('units_sold')
                        ->label('Units Sold')
                        ->state(fn (Product $record) => (string) ((int) ($record->units_sold ?? 0))),
                    TextEntry::make('revenue_total')
                        ->label('Revenue')
                        ->state(fn (Product $record) => (float) ($record->revenue_total ?? 0))
                        ->money('USD'),
                    TextEntry::make('images_count')
                        ->label('Images')
                        ->state(fn (Product $record) => (string) ((int) ($record->images_count ?? 0)))
                        ->badge(),
                    TextEntry::make('variants_count')
                        ->label('Variants')
                        ->state(fn (Product $record) => (string) ((int) ($record->variants_count ?? 0)))
                        ->badge(),
                    TextEntry::make('variants_without_price_count')
                        ->label('Variants Without Price')
                        ->state(fn (Product $record) => (string) ((int) ($record->variants_without_price_count ?? 0)))
                        ->badge(),
                    TextEntry::make('reviews_count')
                        ->label('Reviews')
                        ->state(fn (Product $record) => (string) ((int) ($record->reviews_count ?? 0)))
                        ->badge(),
                ])
                ->columns(5),
            Section::make('Dynamic Pricing')
                ->schema([
                    TextEntry::make('pricing_engine')
                        ->label('Pricing Engine')
                        ->state(fn (Product $record) => data_get($record->pricing_meta, 'margin_source') === 'weight_based' ? 'Weight Based' : 'Legacy')
                        ->badge()
                        ->color(fn (Product $record) => data_get($record->pricing_meta, 'margin_source') === 'weight_based' ? 'success' : 'gray'),
                    TextEntry::make('localWarehouse.name')
                        ->label('Local Warehouse')
                        ->state(fn (Product $record) => $record->localWarehouse?->name ?? '--'),
                    TextEntry::make('pricing_meta.weight_kg')
                        ->label('Weight (kg)')
                        ->state(fn (Product $record) => is_numeric(data_get($record->pricing_meta, 'weight_kg')) ? number_format((float) data_get($record->pricing_meta, 'weight_kg'), 4) : '--'),
                    TextEntry::make('pricing_meta.margin_used')
                        ->label('Margin Used')
                        ->state(fn (Product $record) => is_numeric(data_get($record->pricing_meta, 'margin_used')) ? number_format(((float) data_get($record->pricing_meta, 'margin_used')) * 100, 2) . '%' : '--'),
                    TextEntry::make('pricing_meta.margin_source')
                        ->label('Margin Source')
                        ->state(fn (Product $record) => data_get($record->pricing_meta, 'margin_source') ?: '--')
                        ->badge(),
                    TextEntry::make('pricing_meta.shipping_rate_per_kg')
                        ->label('Shipping / kg')
                        ->state(function (Product $record): string {
                            $value = data_get($record->pricing_meta, 'shipping_rate_per_kg');

                            return is_numeric($value)
                                ? AdminCurrencyService::formatCost((float) $value, $record->currency)
                                : '--';
                        }),
                    TextEntry::make('pricing_meta.external_shipping')
                        ->label('External Shipping')
                        ->state(function (Product $record): string {
                            $value = data_get($record->pricing_meta, 'external_shipping');

                            return is_numeric($value)
                                ? AdminCurrencyService::formatCost((float) $value, $record->currency)
                                : '--';
                        }),
                    TextEntry::make('pricing_meta.cj_shipping')
                        ->label('CJ Shipping')
                        ->state(function (Product $record): string {
                            $value = data_get($record->pricing_meta, 'cj_shipping');

                            return is_numeric($value)
                                ? AdminCurrencyService::formatCost((float) $value, $record->currency)
                                : '--';
                        }),
                    TextEntry::make('pricing_meta.landed_cost')
                        ->label('Landed Cost')
                        ->state(function (Product $record): string {
                            $value = data_get($record->pricing_meta, 'landed_cost');

                            return is_numeric($value)
                                ? AdminCurrencyService::formatCost((float) $value, $record->currency)
                                : '--';
                        }),
                ])
                ->columns(3),
            Section::make('Inventory & Sync')
                ->schema([
                    TextEntry::make('stock_on_hand')
                        ->label('Stock')
                        ->state(fn (Product $record) => (string) ((int) ($record->stock_on_hand ?? 0))),
                    TextEntry::make('shipping_estimate_days')
                        ->label('Shipping Est. Days')
                        ->state(fn (Product $record) => $record->shipping_estimate_days !== null ? (string) $record->shipping_estimate_days : '--'),
                    TextEntry::make('cj_pid')
                        ->label('CJ PID')
                        ->state(fn (Product $record) => $record->cj_pid ?: '--')
                        ->copyable(),
                    TextEntry::make('cj_sync_enabled')
                        ->label('CJ Sync Enabled')
                        ->state(fn (Product $record) => $record->cj_sync_enabled ? 'Yes' : 'No')
                        ->badge(),
                    TextEntry::make('cj_synced_at')
                        ->label('CJ Last Synced')
                        ->state(fn (Product $record) => $record->cj_synced_at?->toDateTimeString() ?? 'Never'),
                    TextEntry::make('cj_removed_from_shelves_at')
                        ->label('Removed From CJ At')
                        ->state(fn (Product $record) => $record->cj_removed_from_shelves_at?->toDateTimeString() ?? '--'),
                    TextEntry::make('cj_removed_reason')
                        ->label('CJ Removed Reason')
                        ->state(fn (Product $record) => $record->cj_removed_reason ?: '--')
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('CJ Payload Details')
                ->schema([
                    TextEntry::make('cj_payload_product_type')
                        ->label('Product type')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'productType'))),
                    TextEntry::make('cj_payload_supplier_name')
                        ->label('Supplier name')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'supplierName'))),
                    TextEntry::make('cj_payload_discount_price')
                        ->label('Discount price')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'discountPrice'))),
                    TextEntry::make('cj_payload_discount_rate')
                        ->label('Discount rate')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'discountPriceRate'))),
                    TextEntry::make('cj_payload_add_mark_status')
                        ->label('Add mark status')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'addMarkStatus'))),
                    TextEntry::make('cj_payload_is_video')
                        ->label('Is video')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'isVideo'))),
                    TextEntry::make('cj_payload_warehouse_inventory_num')
                        ->label('Warehouse inventory')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'warehouseInventoryNum'))),
                    TextEntry::make('cj_payload_total_verified_inventory')
                        ->label('Total verified inventory')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'totalVerifiedInventory'))),
                    TextEntry::make('cj_payload_total_unverified_inventory')
                        ->label('Total unverified inventory')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'totalUnVerifiedInventory'))),
                    TextEntry::make('cj_payload_delivery_cycle')
                        ->label('Delivery cycle')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'deliveryCycle'))),
                    TextEntry::make('cj_payload_video_list')
                        ->label('Video list')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'videoList')))
                        ->columnSpanFull(),
                    TextEntry::make('cj_payload_verified_warehouses')
                        ->label('Verified warehouses')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'verifiedWarehouses')))
                        ->columnSpanFull(),
                    TextEntry::make('cj_payload_my_product')
                        ->label('My product')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'myProduct')))
                        ->columnSpanFull(),
                    TextEntry::make('cj_payload_inventory_info')
                        ->label('Inventory info')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjPayloadValue($record, 'inventoryInfo')))
                        ->columnSpanFull(),
                    TextEntry::make('cj_payload_variant_inventories')
                        ->label('Variant inventories')
                        ->state(fn (Product $record) => self::formatCjPayloadValue(self::cjVariantInventories($record)))
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->visible(fn (Product $record) => filled($record->cj_pid)),
            Section::make('CJ Raw Payload')
                ->schema([
                    TextEntry::make('cj_payload_raw')
                        ->label('CJ payload (raw)')
                        ->state(fn (Product $record) => self::formatJson(self::cjPayload($record)))
                        ->columnSpanFull(),
                    TextEntry::make('cj_variants_raw')
                        ->label('CJ variants (raw)')
                        ->state(fn (Product $record) => self::formatJson(self::cjVariants($record)))
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->collapsible()
                ->visible(fn (Product $record) => filled($record->cj_pid)),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ProductResource\RelationManagers\OrderItemsRelationManager::class,
            \App\Filament\Resources\ProductResource\RelationManagers\ProductVariantsRelationManager::class,
            \App\Filament\Resources\ProductResource\RelationManagers\ProductImagesRelationManager::class,
            \App\Filament\Resources\ProductResource\RelationManagers\MarginLogsRelationManager::class,
        ];
    }

    private static function marginWarning($selling, $cost): ?string
    {
        $sellingValue = self::normalizeAmount($selling);
        $costValue = self::normalizeAmount($cost);

        if ($sellingValue === null || $costValue === null) {
            return null;
        }

        $pricing = PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice($costValue);

        return $sellingValue < $min
            ? "Warning: selling price is below required margin (min {$min})."
            : null;
    }

    private static function normalizeAmount(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $number = (float) $value;

            return is_finite($number) ? $number : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        // Remove any non-numeric characters except decimal points, commas, and minus signs
        $normalized = preg_replace('/[^0-9,.\-]/', '', $normalized) ?? '';
        if ($normalized === '') {
            return null;
        }

        // SAFER decimal parsing - handle European vs American formats
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            // Both comma and dot present - assume comma is thousands separator
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',')) {
            // Only comma present - could be decimal separator or thousands separator
            // Check if it looks like a decimal (only one comma and not at the end)
            $commaCount = substr_count($normalized, ',');
            if ($commaCount === 1 && !str_ends_with($normalized, ',')) {
                // Likely decimal separator
                $parts = explode(',', $normalized);
                if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                    // Decimal with 2 or fewer digits after comma - treat as decimal
                    $normalized = str_replace(',', '.', $normalized);
                } else {
                    // Likely thousands separator
                    $normalized = str_replace(',', '', $normalized);
                }
            } else {
                // Multiple commas or comma at end - treat as thousands separator
                $normalized = str_replace(',', '', $normalized);
            }
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;

        return is_finite($number) ? $number : null;
    }

    private static function syncStatus(Product $record): string
    {
        if (! $record->cj_pid && self::isAliExpressProduct($record)) {
            return 'AliExpress';
        }

        if (! $record->cj_pid) {
            return 'Local';
        }

        if ($record->cj_removed_from_shelves_at) {
            return 'Removed from CJ';
        }

        if (! $record->cj_sync_enabled) {
            return 'Sync off';
        }

        if (! $record->cj_synced_at) {
            return 'Never';
        }

        $cutoff = now()->subHours(self::CJ_SYNC_STALE_HOURS);

        return $record->cj_synced_at->lt($cutoff) ? 'Out of sync' : 'Synced';
    }

    private static function syncStatusColor(Product $record): string
    {
        return match (self::syncStatus($record)) {
            'Synced' => 'success',
            'Out of sync' => 'warning',
            'Removed from CJ' => 'danger',
            'Never' => 'danger',
            'Sync off' => 'gray',
            'AliExpress' => 'warning',
            default => 'gray',
        };
    }

    private static function cjAvailability(Product $record): string
    {
        if (! $record->cj_pid) {
            return 'N/A';
        }

        return $record->cj_removed_from_shelves_at ? 'Removed' : 'Available';
    }

    private static function cjAvailabilityColor(Product $record): string
    {
        if (! $record->cj_pid) {
            return 'gray';
        }

        return $record->cj_removed_from_shelves_at ? 'danger' : 'success';
    }

    private static function sourceLabel(Product $record): string
    {
        if ($record->cj_pid) {
            return 'CJ';
        }

        if (self::isAliExpressProduct($record)) {
            return 'AliExpress';
        }

        return 'Local';
    }

    private static function isAliExpressProduct(Product $record): bool
    {
        $attributes = is_array($record->attributes) ? $record->attributes : [];
        $aliItemId = data_get($attributes, 'ali_item_id');
        $supplierCode = data_get($attributes, 'supplier_code');

        return ($aliItemId !== null && $aliItemId !== '') || $supplierCode === 'aliexpress';
    }

    private static function formatChangedFields(Product $record): string
    {
        $fields = is_array($record->cj_last_changed_fields) ? $record->cj_last_changed_fields : [];
        if ($fields === []) {
            return '--';
        }

        return collect($fields)
            ->take(3)
            ->implode(', ');
    }

    private static function mediaStatus(Product $record): string
    {
        return $record->images->isNotEmpty() ? 'Media complete' : 'Missing media';
    }

    private static function mediaStatusColor(Product $record): string
    {
        return $record->images->isNotEmpty() ? 'success' : 'warning';
    }

    private static function cjPayload(?Product $record): array
    {
        if (! $record) {
            return [];
        }

        $attributes = $record->getAttribute('attributes');
        if (is_array($attributes)) {
            $payload = $attributes['cj_payload'] ?? null;
            if (is_array($payload)) {
                return $payload;
            }
        }

        return is_array($record->cj_last_payload) ? $record->cj_last_payload : [];
    }

    private static function cjPayloadValue(?Product $record, string $key): mixed
    {
        $payload = self::cjPayload($record);

        return $payload[$key] ?? null;
    }

    private static function cjVariants(?Product $record): array
    {
        if (! $record) {
            return [];
        }

        $attributes = $record->getAttribute('attributes');
        if (is_array($attributes)) {
            $variants = $attributes['cj_variants'] ?? null;
            if (is_array($variants)) {
                return $variants;
            }
        }

        return [];
    }

    private static function cjVariantInventories(?Product $record): array
    {
        $variants = self::cjVariants($record);
        if ($variants === []) {
            return [];
        }

        $summary = [];
        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $inventories = $variant['inventories'] ?? null;
            if (! is_array($inventories) || $inventories === []) {
                continue;
            }

            $summary[] = [
                'vid' => $variant['vid'] ?? $variant['variantId'] ?? null,
                'variantKey' => $variant['variantKey'] ?? $variant['variantNameEn'] ?? null,
                'inventories' => $inventories,
            ];
        }

        return $summary;
    }

    private static function formatJson(mixed $value): string
    {
        if (! is_array($value) || $value === []) {
            return '--';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '--';
    }

    private static function formatCjPayloadValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '--';
        }

        return (string) $value;
    }

    /**
     * Extract CJ payload summary with focus on selling price
     */
    private static function extractCjPayloadSummary(mixed $payload): string
    {
        if (!$payload) {
            return '';
        }

        $payloadArray = is_array($payload) ? $payload : [];
        $summary = [];

        // Extract key information for display - prioritize selling price
        $keyFields = [
            'selling_price' => '💰 Selling Price',
            'pid' => 'PID',
            'productCost' => 'Cost',
            'productTitle' => 'Title',
            'categoryName' => 'Category',
            'totalInventoryNum' => 'Stock'
        ];

        foreach ($keyFields as $field => $label) {
            if (isset($payloadArray[$field])) {
                $value = $payloadArray[$field];

                // Special formatting for selling price
                if ($field === 'selling_price') {
                    $price = is_numeric($value) ? '$' . number_format((float) $value, 2) : json_encode($value);
                    $summary[] = $label . ': ' . $price;
                } else {
                    $displayValue = is_string($value) ? substr($value, 0, 30) . (strlen($value) > 30 ? '...' : '') : json_encode($value);
                    $summary[] = $label . ': ' . $displayValue;
                }
            }
        }

        return implode(', ', $summary);
    }

    /**
     * Debug method to analyze margin discrepancies
     */
    public static function analyzeMarginDiscrepancies(): array
    {
        $allProducts = Product::all();
        $analysis = [
            'total_products' => $allProducts->count(),
            'margin_status_counts' => [],
            'filter_counts' => [],
            'discrepancies' => []
        ];

        // Count by margin status (what the column shows)
        $analysis['margin_status_counts'] = $allProducts->groupBy(function ($product) {
            $cost = self::normalizeAmount($product->cost_price);
            $selling = self::normalizeAmount($product->selling_price);

            if ($cost === null || $selling === null) {
                return 'Missing';
            }

            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
            $min = $pricing->minSellingPrice($cost);

            if ($selling < $min) {
                return 'Below Required';
            }

            return 'OK';
        })->map->count();

        // Count by filter logic (what the filter shows)
        $analysis['filter_counts'] = [
            'margin_not_set' => Product::where(function ($query) {
                $query->whereNull('cost_price')
                    ->orWhereNull('selling_price')
                    ->orWhere('selling_price', '<', 0)
                    ->orWhere('cost_price', '<', 0)
                    ->orWhere(function ($marginQuery) {
                        $marginQuery->whereNotNull('cost_price')
                            ->whereNotNull('selling_price')
                            ->where('cost_price', '>', 0)
                            ->where('selling_price', '>', 0)
                            ->whereRaw('selling_price < (
                                CASE
                                    WHEN cost_price <= 5 THEN cost_price * 2.5
                                    WHEN cost_price <= 10 THEN cost_price * 2.0
                                    WHEN cost_price <= 20 THEN cost_price * 1.8
                                    WHEN cost_price <= 50 THEN cost_price * 1.6
                                    WHEN cost_price <= 100 THEN cost_price * 1.5
                                    WHEN cost_price <= 200 THEN cost_price * 1.4
                                    WHEN cost_price <= 500 THEN cost_price * 1.3
                                    ELSE cost_price * 1.25
                                END
                            )');
                    });
            })->count()
        ];

        // Find discrepancies
        $analysis['discrepancies'] = $allProducts->filter(function ($product) {
            $cost = self::normalizeAmount($product->cost_price);
            $selling = self::normalizeAmount($product->selling_price);

            // Margin status logic
            $marginStatus = 'OK';
            if ($cost === null || $selling === null) {
                $marginStatus = 'Missing';
            } else {
                $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
                $min = $pricing->minSellingPrice($cost);
                if ($selling < $min) {
                    $marginStatus = 'Below Required';
                }
            }

            // Filter logic
            $matchesFilter = (
                is_null($product->cost_price) ||
                is_null($product->selling_price) ||
                $product->selling_price < 0 ||
                $product->cost_price < 0 ||
                ($product->cost_price > 0 && $product->selling_price > 0 && self::isBelowRequiredMargin($product->cost_price, $product->selling_price))
            );

            // Discrepancy: margin status shows problem but filter doesn't match
            return in_array($marginStatus, ['Missing', 'Below Required']) && !$matchesFilter;
        })->take(10)->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'margin_status' => self::getMarginStatus($product),
                'matches_filter' => self::matchesMarginFilter($product)
            ];
        })->values();

        return $analysis;
    }

    private static function isBelowRequiredMargin(float $cost, float $selling): bool
    {
        return $selling < ($cost * 1.50); // 50% margin
    }

    private static function getMarginStatus(Product $product): string
    {
        $cost = self::normalizeAmount($product->cost_price);
        $selling = self::normalizeAmount($product->selling_price);

        if ($cost === null || $selling === null) {
            return 'Missing';
        }

        $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice($cost);

        if ($selling < $min) {
            return 'Below Required';
        }

        return 'OK';
    }

    private static function matchesMarginFilter(Product $product): bool
    {
        return (
            is_null($product->cost_price) ||
            is_null($product->selling_price) ||
            $product->selling_price < 0 ||
            $product->cost_price < 0 ||
            ($product->cost_price > 0 && $product->selling_price > 0 && self::isBelowRequiredMargin($product->cost_price, $product->selling_price))
        );
    }

    /**
     * Centralized margin validation logic
     * Ensures consistency between column display and filtering
     */
    public static function validateProductMargin(Product $product): array
    {
        $result = [
            'status' => 'OK',
            'issues' => [],
            'recommended_selling_price' => null,
            'current_margin_percent' => null,
            'required_margin_percent' => null
        ];

        $cost = self::normalizeAmount($product->cost_price);
        $selling = self::normalizeAmount($product->selling_price);

        // Check for missing prices
        if ($cost === null) {
            $result['status'] = 'Missing';
            $result['issues'][] = 'Cost price is missing or invalid';
            return $result;
        }

        if ($selling === null) {
            $result['status'] = 'Missing';
            $result['issues'][] = 'Selling price is missing or invalid';
            return $result;
        }

        // Check for negative prices
        if ($cost < 0) {
            $result['status'] = 'Missing';
            $result['issues'][] = 'Cost price is negative';
            return $result;
        }

        if ($selling < 0) {
            $result['status'] = 'Missing';
            $result['issues'][] = 'Selling price is negative';
            return $result;
        }

        // Check margin requirements
        $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
        $minSellingPrice = $pricing->minSellingPrice($cost);

        if ($selling < $minSellingPrice) {
            $result['status'] = 'Below Required';
            $result['issues'][] = 'Selling price is below required minimum';
            $result['recommended_selling_price'] = $minSellingPrice;
            $result['current_margin_percent'] = (($selling - $cost) / $cost) * 100;
            $result['required_margin_percent'] = (($minSellingPrice - $cost) / $cost) * 100;
        }

        return $result;
    }

    /**
     * Enhanced margin setting with validation and logging
     */
    public static function repriceProductWithCurrentEngine(Product $product, array $options = []): array
    {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {
            $cost = self::normalizeAmount($product->cost_price);
            if ($cost === null || $cost <= 0) {
                $result['message'] = 'Invalid cost price for dynamic pricing.';
                return $result;
            }

            $warehouse = $product->relationLoaded('localWarehouse')
                ? $product->localWarehouse
                : ($product->local_warehouse_id ? \App\Models\LocalWareHouse::query()->find($product->local_warehouse_id) : null);

            $weightKg = self::resolveProductWeightKg($product);
            $cjShipping = (float) (data_get($product->pricing_meta, 'cj_shipping') ?? 0);
            $pricing = PricingService::makeFromConfig()->calculate(
                productCost: $cost,
                weight: $weightKg,
                cjShipping: $cjShipping,
                warehouse: $warehouse,
                currency: (string) ($product->currency ?: 'USD'),
                options: [
                    'category_id' => $product->category_id,
                    'warehouse_id' => $warehouse?->id,
                ],
            );

            $oldSellingPrice = $product->selling_price;
            $updateData = [
                'selling_price' => $pricing->basePrice,
                'local_warehouse_id' => $warehouse?->id,
                'pricing_meta' => $pricing->pricingMeta,
            ];

            if ($options['activate_if_valid'] ?? false) {
                $updateData['is_active'] = true;
                $updateData['status'] = 'active';
            }

            $product->update($updateData);

            app(\App\Services\ProductMarginLogger::class)->logProduct($product, [
                'event' => 'dynamic_reprice',
                'source' => 'manual',
                'old_selling_price' => $oldSellingPrice,
                'new_selling_price' => $pricing->basePrice,
                'notes' => $options['reason'] ?? 'Manual dynamic repricing',
            ]);

            $variantCount = 0;
            if ($options['apply_to_variants'] ?? true) {
                $product->loadMissing('variants');
                foreach ($product->variants as $variant) {
                    $variantCost = self::normalizeAmount($variant->cost_price);
                    if ($variantCost === null || $variantCost <= 0) {
                        continue;
                    }

                    $variantMeta = is_array($variant->metadata ?? null) ? $variant->metadata : [];
                    $variantWarehouseId = $variantMeta['local_warehouse_id'] ?? null;
                    $variantWarehouse = $variantWarehouseId
                        ? \App\Models\LocalWareHouse::query()->find((int) $variantWarehouseId)
                        : $warehouse;

                    $variantWeightKg = self::resolveVariantWeightKg($variant, $product);
                    $variantCjShipping = (float) (data_get($variantMeta, 'pricing_meta.cj_shipping') ?? $cjShipping);
                    $variantPricing = PricingService::makeFromConfig()->calculate(
                        productCost: $variantCost,
                        weight: $variantWeightKg,
                        cjShipping: $variantCjShipping,
                        warehouse: $variantWarehouse,
                        currency: (string) ($variant->currency ?: $product->currency ?: 'USD'),
                        options: [
                            'category_id' => $product->category_id,
                            'warehouse_id' => $variantWarehouse?->id,
                        ],
                    );

                    $oldVariantPrice = $variant->price;
                    $variantMeta['local_warehouse_id'] = $variantWarehouse?->id;
                    $variantMeta['pricing_meta'] = $variantPricing->pricingMeta;

                    $variant->update([
                        'price' => $variantPricing->basePrice,
                        'metadata' => $variantMeta,
                    ]);

                    app(\App\Services\ProductMarginLogger::class)->logVariant($variant, [
                        'event' => 'dynamic_reprice',
                        'source' => 'manual',
                        'old_selling_price' => $oldVariantPrice,
                        'new_selling_price' => $variantPricing->basePrice,
                        'notes' => $options['reason'] ?? 'Manual dynamic repricing',
                    ]);

                    $variantCount++;
                }
            }

            $result['success'] = true;
            $result['message'] = "Repriced product with weight-based pricing" . (($options['apply_to_variants'] ?? true) ? " and {$variantCount} variant(s)." : '.');

            return $result;
        } catch (\Throwable $e) {
            $result['message'] = 'Error recalculating pricing: ' . $e->getMessage();
            return $result;
        }
    }

    public static function setProductMargin(Product $product, float $marginPercent, array $options = []): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'old_selling_price' => $product->selling_price,
            'new_selling_price' => null,
            'warnings' => []
        ];

        try {
            if (PricingService::usesNewEngine()) {
                $result['message'] = 'Manual margin updates are blocked while pricing.use_new_engine is enabled.';
                return $result;
            }

            $cost = self::normalizeAmount($product->cost_price);
            if ($cost === null || $cost < 0) {
                $result['message'] = 'Invalid cost price for margin calculation';
                return $result;
            }

            $pricing = \App\Domain\Products\Services\PricingService::makeFromConfig();
            $currency = (string) ($product->currency ?: 'USD');
            $priceResult = $pricing->calculateSellingPrice(
                cost: $cost,
                currency: $currency,
                marginPercent: $marginPercent,
                categoryId: $product->category_id,
            );
            $newSellingPrice = (float) ($priceResult['base_price'] ?? 0);

            // Validate the new price
            $validation = self::validateProductMargin(new Product(['cost_price' => $cost, 'selling_price' => $newSellingPrice]));

            if ($validation['status'] === 'Missing') {
                $result['message'] = 'Calculated price is invalid: ' . implode(', ', $validation['issues']);
                return $result;
            }

            // Update the product
            $oldSellingPrice = $product->selling_price;
            $updateData = ['selling_price' => $newSellingPrice];

            if ($options['activate_if_valid'] ?? false) {
                $updateData['is_active'] = true;
                $updateData['status'] = 'active';
            }

            $product->update($updateData);

            $result['success'] = true;
            $result['new_selling_price'] = $newSellingPrice;
            $result['message'] = 'Margin set successfully';

            if ($validation['status'] === 'Below Required') {
                $result['warnings'][] = 'New price is still below required minimum';
            }

            // Log the margin change
            app(\App\Services\ProductMarginLogger::class)->logProduct($product, [
                'event' => 'manual_set',
                'source' => 'manual',
                'old_selling_price' => $oldSellingPrice,
                'new_selling_price' => $newSellingPrice,
                'notes' => $options['reason'] ?? 'Manual margin adjustment',
            ]);

        } catch (\Exception $e) {
            $result['message'] = 'Error setting margin: ' . $e->getMessage();
        }

        return $result;
    }

    private static function resolveProductWeightKg(Product $product): float
    {
        $pricingWeight = self::normalizeAmount(data_get($product->pricing_meta, 'weight_kg'));
        if ($pricingWeight !== null && $pricingWeight > 0) {
            return $pricingWeight;
        }

        $attributes = is_array($product->attributes ?? null) ? $product->attributes : [];
        $payloadWeight = self::extractWeightKgFromRaw(data_get($attributes, 'cj_payload.productWeight'));
        if ($payloadWeight > 0) {
            return $payloadWeight;
        }

        $variantWeight = $product->variants()
            ->whereNotNull('weight_grams')
            ->orderByDesc('weight_grams')
            ->value('weight_grams');

        return $variantWeight ? round(((float) $variantWeight) / 1000, 4) : 0.0;
    }

    private static function resolveVariantWeightKg(mixed $variant, Product $product): float
    {
        $meta = is_array($variant->metadata ?? null) ? $variant->metadata : [];
        $pricingWeight = self::normalizeAmount(data_get($meta, 'pricing_meta.weight_kg'));
        if ($pricingWeight !== null && $pricingWeight > 0) {
            return $pricingWeight;
        }

        $weightGrams = self::normalizeAmount($variant->weight_grams);
        if ($weightGrams !== null && $weightGrams > 0) {
            return round($weightGrams / 1000, 4);
        }

        $cjVariantWeight = self::extractWeightKgFromRaw(data_get($meta, 'cj_variant.variantWeight'));
        if ($cjVariantWeight > 0) {
            return $cjVariantWeight;
        }

        return self::resolveProductWeightKg($product);
    }

    private static function extractWeightKgFromRaw(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            return $numeric > 10 ? round($numeric / 1000, 4) : round($numeric, 4);
        }

        if (is_string($value) && preg_match_all('/-?\d+(?:\.\d+)?/', $value, $matches) && ! empty($matches[0])) {
            $numeric = (float) end($matches[0]);
            return $numeric > 10 ? round($numeric / 1000, 4) : round($numeric, 4);
        }

        return 0.0;
    }

    /**
     * Bulk margin setting with comprehensive validation
     */
    public static function setBulkMargins(array $productIds, float $marginPercent, array $options = []): array
    {
        $results = [
            'total' => count($productIds),
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];

        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $result = self::setProductMargin($product, $marginPercent, $options);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'error' => $result['message']
                ];
            }
        }

        // Handle skipped products (not found)
        $results['skipped'] = $results['total'] - $products->count();

        return $results;
    }

    /**
     * Get products without margin for targeted operations
     */
    public static function getProductsWithoutMargin(?int $limit = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::where(function ($query) {
            $query->where(function ($subQuery) {
                // Missing prices
                $subQuery->whereNull('cost_price')
                    ->orWhereNull('selling_price')
                    ->orWhere('cost_price', '<', 0)
                    ->orWhere('selling_price', '<', 0);
            })
            ->orWhere(function ($subQuery) {
                // Below required margin
                $subQuery->whereNotNull('cost_price')
                    ->whereNotNull('selling_price')
                    ->where('cost_price', '>', 0)
                    ->where('selling_price', '>', 0)
                    ->whereRaw('selling_price < (cost_price * 1.50)');
            });
        });

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
