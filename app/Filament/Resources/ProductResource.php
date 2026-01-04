<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Domain\Products\Services\CjProductImportService;
use App\Domain\Products\Services\PricingService;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Jobs\TranslateProductJob;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductResource extends BaseResource
{
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

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 10;

    private const CJ_SYNC_STALE_HOURS = 24;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['images', 'variants'])->with('images');
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
                            '<div class="text-sm text-slate-600 space-y-1">'
                            . '<p><strong>Product price:</strong> default for all variants.</p>'
                            . '<p><strong>Variant price:</strong> overrides product price when set.</p>'
                            . '<p><strong>Margin:</strong> calculated from cost; must meet minimum threshold.</p>'
                            . '</div>'
                        ),
                   TextInput::make('selling_price')
                        ->label('Selling price')
                        ->helperText('Default product-level price; variants can override.')
                        ->numeric()
                        ->required()
                        ->prefix('$')
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
                        ->label('Cost price')
                        ->helperText('Baseline cost; used when variant cost is missing.')
                        ->numeric()
                        ->required()
                        ->prefix('$')
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
        $globalSyncStatus = self::getGlobalSyncStatus();
        $importedCount = self::getImportedCount();
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('imported_count')
                    ->view('filament.tables.columns.imported-count')
                    ->label('Imported Count')
                    ->visible(fn () => true),
                Tables\Columns\ViewColumn::make('global_sync_status')
                    ->view('filament.tables.columns.global-sync-status')
                    ->label('Global Sync Status')
                    ->visible(fn () => true),
                Tables\Columns\ImageColumn::make('primary_image')
                    ->label('Image')
                    ->getStateUsing(fn (Product $record) => $record->images->sortBy('position')->first()?->url)
                    ->square(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (Product $record) => $record->cj_pid ? 'CJ' : 'Local')
                    ->badge()
                    ->color(fn (string $state) => $state === 'CJ' ? 'info' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sync_status')
                    ->label('Sync')
                    ->getStateUsing(fn (Product $record) => self::syncStatus($record))
                    ->badge()
                    ->color(fn (Product $record) => self::syncStatusColor($record))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('translation_status')
                    ->label('Translation')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cj_pid')
                    ->label('CJ PID')
                    ->copyable()
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured')->toggleable(),
                Tables\Columns\TextColumn::make('selling_price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('cost_price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('stock_on_hand')
                    ->label('Stock')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('images_count')
                    ->label('Images')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Columns\TextColumn::make('cj_last_changed_fields')
                    ->label('Recent changes')
                    ->formatStateUsing(fn (Product $record) => self::formatChangedFields($record))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (Product $record) => is_array($record->cj_last_changed_fields) ? implode(', ', $record->cj_last_changed_fields) : null),
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
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('cj')
                    ->label('CJ')
                    ->query(fn ($query) => $query->whereNotNull('cj_pid'))
                    ->toggle(),
                Tables\Filters\Filter::make('local')
                    ->label('Local')
                    ->query(fn ($query) => $query->whereNull('cj_pid'))
                    ->toggle(),
                Tables\Filters\Filter::make('sync_enabled')
                    ->label('Sync Enabled')
                    ->query(fn ($query) => $query->where('cj_sync_enabled', true))
                    ->toggle(),
                Tables\Filters\Filter::make('out_of_sync')
                    ->label('Out of Sync')
                    ->query(function ($query) {
                        $cutoff = now()->subHours(self::CJ_SYNC_STALE_HOURS);

                        return $query
                            ->whereNotNull('cj_pid')
                            ->where('cj_sync_enabled', true)
                            ->where(function ($inner) use ($cutoff) {
                                $inner->whereNull('cj_synced_at')
                                    ->orWhere('cj_synced_at', '<', $cutoff);
                            });
                    })
                    ->toggle(),
                Tables\Filters\Filter::make('missing_images')
                    ->label('Missing Images')
                    ->query(fn ($query) => $query->doesntHave('images'))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('sync_flag')
                    ->label('Sync')
                    ->options([
                        'enabled' => 'Enabled',
                        'disabled' => 'Disabled',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value === 'enabled') {
                            $query->where('cj_sync_enabled', true);
                        } elseif ($value === 'disabled') {
                            $query->whereNotNull('cj_pid')->where('cj_sync_enabled', false);
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
 ActionsEditAction::make(),
                Action::make('quickEdit')
                    ->label('Quick edit')
                    ->icon('heroicon-o-pencil-square')
                    ->slideOver()
                    ->schema([
                        TextInput::make('selling_price')->label('Selling price')->helperText('Default product-level price; variants may override.')->numeric()->required(),
                        TextInput::make('cost_price')->label('Cost price')->numeric()->required(),
                        TextInput::make('stock_on_hand')->label('Stock on hand')->numeric()->minValue(0),
                        Toggle::make('is_active')->label('Active'),
                        Toggle::make('is_featured')->label('Featured'),
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

                        TranslateProductJob::dispatch((int) $record->id, ['en', 'fr'], 'en', false);

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
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Product $record) => route('products.show', $record->slug))
                    ->openUrlInNewTab(),
                Action::make('toggleActive')
                    ->label('Activate/Deactivate')
                    ->icon('heroicon-o-power')
                    ->action(fn (Product $record) => $record->update(['is_active' => ! $record->is_active])),
                ])
               
            ])
            ->toolbarActions([
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
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $importer = app(CjProductImportService::class);
                            $synced = 0;
                            $skipped = 0;
                            $errors = 0;

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
                    BulkAction::make('syncMedia')
                        ->label('Sync Media')
                        ->icon('heroicon-o-photo')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $importer = app(CjProductImportService::class);
                            $synced = 0;
                            $skipped = 0;
                            $errors = 0;

                            foreach ($records as $record) {
                                if (! $record->cj_pid) {
                                    $skipped++;
                                    continue;
                                }

                                try {
                                    $updated = $importer->syncMedia($record, [
                                        'respectSyncFlag' => false,
                                        'respectLocks' => true,
                                    ]);
                                } catch (\Throwable) {
                                    $errors++;
                                    continue;
                                }

                                if ($updated) {
                                    $synced++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title('Media sync complete')
                                ->body("Synced {$synced} product(s), skipped {$skipped}, errors {$errors}.")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('setMargin')
                        ->label('Set Margin %')
                        ->icon('heroicon-o-calculator')
                        ->form([
                            TextInput::make('margin_percent')
                                ->label('Margin %')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                            Toggle::make('apply_to_variants')
                                ->label('Apply to variants')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $margin = (float) $data['margin_percent'];
                            $applyVariants = (bool) ($data['apply_to_variants'] ?? true);

                            $records->load('variants');

                            $updated = 0;
                            $skipped = 0;
                            $variantUpdated = 0;
                            $variantSkipped = 0;

                            $records->each(function (Product $record) use ($margin, $applyVariants, &$updated, &$skipped, &$variantUpdated, &$variantSkipped): void {
                                if (! is_numeric($record->cost_price)) {
                                    $skipped++;
                                    return;
                                }

                                $record->update([
                                    'selling_price' => round(((float) $record->cost_price) * (1 + $margin / 100), 2),
                                ]);
                                $updated++;

                                if ($applyVariants) {
                                    $record->variants->each(function ($variant) use ($margin, &$variantUpdated, &$variantSkipped): void {
                                        if (! is_numeric($variant->cost_price)) {
                                            $variantSkipped++;
                                            return;
                                        }
                                        $variant->update([
                                            'price' => round(((float) $variant->cost_price) * (1 + $margin / 100), 2),
                                        ]);
                                        $variantUpdated++;
                                    });
                                }
                            });

                            $body = "Updated $updated product(s) and $variantUpdated variant(s).";
                            if ($skipped > 0 || $variantSkipped > 0) {
                                $body .= " Skipped $skipped product(s) and $variantSkipped variant(s) due to missing or invalid cost price.";
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
                    BulkAction::make('translate')
                        ->label('Translate')
                        ->icon('heroicon-o-language')
                        ->form([
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

                            foreach ($records as $record) {
                                TranslateProductJob::dispatch((int) $record->id, $locales, $source, $force);
                            }

                            Notification::make()
                                ->title('Translations queued')
                                ->body("Queued {$records->count()} product(s).")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('generateSeo')
                        ->label('Generate SEO')
                        ->icon('heroicon-o-sparkles')
                        ->form([
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
                    ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ProductResource\RelationManagers\ProductVariantsRelationManager::class,
            \App\Filament\Resources\ProductResource\RelationManagers\ProductImagesRelationManager::class,
        ];
    }

    private static function marginWarning($selling, $cost): ?string
    {
        if (! $selling || ! $cost) {
            return null;
        }

        $pricing = PricingService::makeFromConfig();
        $min = $pricing->minSellingPrice((float) $cost);

        return $selling < $min
            ? "Warning: selling price is below required margin (min {$min})."
            : null;
    }

    private static function syncStatus(Product $record): string
    {
        if (! $record->cj_pid) {
            return 'Local';
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
            'Never' => 'danger',
            'Sync off' => 'gray',
            default => 'gray',
        };
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
}

