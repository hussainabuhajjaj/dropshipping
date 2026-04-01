<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCollectionResource\RelationManagers;

use App\Filament\Resources\StorefrontCollectionResource;
use App\Models\Category;
use App\Services\Storefront\HomeBuilderService;
use App\Jobs\ApplyProductMarginChunkJob;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Products';

    private function positionSchema(): array
    {
        return [
            Forms\Components\TextInput::make('pivot.position')
                ->label('Position')
                ->numeric()
                ->default(0)
                ->required(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumb')
                    ->label('')
                    ->square()
                    ->size(44)
                    ->getStateUsing(function ($record): ?string {
                        $homeBuilder = app(HomeBuilderService::class);

                        $url = $homeBuilder->normalizeImage(data_get($record, 'first_image_url'));
                        if ($url) {
                            return $this->maybeProxyCjUrl($url);
                        }

                        $cj = is_array($record->cj_last_payload ?? null) ? $record->cj_last_payload : [];
                        foreach ([
                            'bigImage',
                            'productImage',
                            'mainImage',
                            'data.bigImage',
                            'data.productImage',
                            'data.mainImage',
                        ] as $key) {
                            $candidate = Arr::get($cj, $key);
                            $url = $homeBuilder->normalizeImage(is_string($candidate) ? $candidate : null);
                            if ($url) {
                                return $this->maybeProxyCjUrl($url);
                            }
                        }

                        return null;
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Price')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable()
                    ->toggleable(),
                       Tables\Columns\TextColumn::make('cost_price')
                    ->label('Cost Price')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('variants_min_price')
                    ->label('Min variant')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('variants_max_price')
                    ->label('Max variant')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reviews_avg_rating')
                    ->label('Rating')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('latestMarginLog.new_margin_percent')
                    ->label('Margin %')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pivot.position')
                    ->label('Position')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
            ])
            ->defaultSort('storefront_collection_products.position')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => ['' => 'All Categories'] + Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(function (string $search) {
                        return Category::query()
                            ->where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                      ->orWhere('slug', 'like', "%{$search}%");
                            })
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id');
                    })
                    ->getOptionLabelUsing(function ($value) {
                        if (empty($value)) {
                            return 'All Categories';
                        }
                        $category = Category::find($value);
                        return $category?->name ?? $value;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        // Handle empty/null values - show all products
                        if (blank($value)) {
                            return $query;
                        }

                        // Debug: Log the filter value
                        \Log::info('Category filter applied', [
                            'category_id' => $value,
                            'table' => $query->getModel()->getTable(),
                            'sql_before' => $query->toSql()
                        ]);

                        // Explicitly filter by category_id
                        $query = $query->where('products.category_id', $value);

                        // Debug: Log the final query
                        \Log::info('Category filter query', [
                            'sql_after' => $query->toSql(),
                            'bindings' => $query->getBindings()
                        ]);

                        return $query;
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('min')
                            ->label('Min price')
                            ->numeric(),
                        Forms\Components\TextInput::make('max')
                            ->label('Max price')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $min = isset($data['min']) && $data['min'] !== '' ? (float) $data['min'] : null;
                        $max = isset($data['max']) && $data['max'] !== '' ? (float) $data['max'] : null;
                        return $query->priceRange($min, $max);
                    })
                    ->indicateUsing(function (array $data): string {
                        $min = $data['min'] ?? null;
                        $max = $data['max'] ?? null;
                        if ($min && $max) {
                            return "Price: {$min} - {$max}";
                        } elseif ($min) {
                            return "Min: {$min}";
                        } elseif ($max) {
                            return "Max: {$max}";
                        }
                        return '';
                    }),
                Tables\Filters\Filter::make('min_rating')
                    ->form([
                        Forms\Components\Select::make('rating')
                            ->options([
                                5 => '5 stars',
                                4 => '4 stars & up',
                                3 => '3 stars & up',
                                2 => '2 stars & up',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $rating = isset($data['rating']) && $data['rating'] !== '' ? (float) $data['rating'] : null;
                        if (! $rating) {
                            return $query;
                        }
                        return $query->having('reviews_avg_rating', '>=', $rating);
                    })
                    ->indicateUsing(function (array $data): string {
                        $rating = $data['rating'] ?? null;
                        return $rating ? "Rating: {$rating}+ stars" : '';
                    }),
                Tables\Filters\Filter::make('min_margin')
                    ->form([
                        Forms\Components\TextInput::make('margin')
                            ->label('Min margin %')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $margin = isset($data['margin']) && $data['margin'] !== '' ? (float) $data['margin'] : null;
                        if ($margin === null) {
                            return $query;
                        }
                        return $query->whereHas('latestMarginLog', fn (Builder $q) => $q->where('new_margin_percent', '>=', $margin));
                    })
                    ->indicateUsing(function (array $data): string {
                        $margin = $data['margin'] ?? null;
                        return $margin ? "Margin: {$margin}%" : '';
                    }),
                Tables\Filters\SelectFilter::make('supplier')
                    ->label('Supplier')
                    ->options([
                        'cj' => 'CJ Dropshipping',
                        'ali' => 'AliExpress',
                        'manual' => 'Manual Entry',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $supplier = $data['value'] ?? null;
                        if (!$supplier) {
                            return $query;
                        }

                        return match ($supplier) {
                            'cj' => $query->whereNotNull('cj_pid'),
                            'ali' => $query->whereNotNull('attributes->ali_item_id'),
                            'manual' => $query->whereNull('cj_pid')->whereNull('attributes->ali_item_id'),
                            default => $query,
                        };
                    }),
                Tables\Filters\Filter::make('has_images')
                    ->label('Has Images')
                    ->query(fn (Builder $query): Builder => $query->whereHas('images'))
                    ->toggle(),
                Tables\Filters\Filter::make('no_images')
                    ->label('Missing Images')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('images'))
                    ->toggle(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('remove_from_collection')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $this->ownerRecord->products()->detach($record->id);

                        Notification::make()
                            ->success()
                            ->title('Product Removed')
                            ->body('The product was removed from this collection.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('add_products')
                    ->label('Add Products')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => StorefrontCollectionResource::getUrl('pick-products', ['record' => $this->ownerRecord]))
                    ->color('success'),

                DetachBulkAction::make('remove_from_collection')
                    ->label('Remove from Collection')
                    ->icon('heroicon-o-trash')
                    ->color('danger'),

                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            if ($ids === []) {
                                return;
                            }

                            Product::query()->whereIn('id', $ids)->update([
                                'is_active' => true,
                                'status' => 'active',
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Products Activated')
                                ->body(count($ids) . ' products activated successfully.')
                                ->send();
                        }),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(function ($records): void {
                            $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            if ($ids === []) {
                                return;
                            }

                            Product::query()->whereIn('id', $ids)->update([
                                'is_active' => false,
                                'status' => 'draft',
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Products Deactivated')
                                ->body(count($ids) . ' products deactivated successfully.')
                                ->send();
                        }),
                    BulkAction::make('feature')
                        ->label('Mark Featured')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            if ($ids === []) {
                                return;
                            }

                            Product::query()->whereIn('id', $ids)->update([
                                'is_featured' => true,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Products Featured')
                                ->body(count($ids) . ' products marked as featured.')
                                ->send();
                        }),
                    BulkAction::make('unfeature')
                        ->label('Remove Featured')
                        ->requiresConfirmation()
                        ->color('warning')
                        ->action(function ($records): void {
                            $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            if ($ids === []) {
                                return;
                            }

                            Product::query()->whereIn('id', $ids)->update([
                                'is_featured' => false,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Featured Status Removed')
                                ->body(count($ids) . ' products removed from featured.')
                                ->send();
                        }),
                    BulkAction::make('apply_margin')
                        ->label('Apply Margin')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\TextInput::make('margin')
                                ->label('Margin %')
                                ->numeric()
                                ->minValue(0)
                                ->default(30)
                                ->required(),
                            Forms\Components\Toggle::make('apply_variants')
                                ->label('Apply to variants')
                                ->default(true),
                        ])
                        ->action(function ($records, array $data): void {
                            $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            if ($ids === []) {
                                return;
                            }

                            $margin = (float) ($data['margin'] ?? 0);
                            $applyVariants = (bool) ($data['apply_variants'] ?? true);

                            foreach (array_chunk($ids, 200) as $chunk) {
                                ApplyProductMarginChunkJob::dispatch(
                                    productIds: $chunk,
                                    margin: $margin,
                                    applyVariants: $applyVariants,
                                );
                            }

                            Notification::make()
                                ->success()
                                ->title('Margin Job Queued')
                                ->body('Margin calculation jobs queued for ' . count($ids) . ' products.')
                                ->send();
                        }),
                    BulkAction::make('reorder')
                        ->label('Reorder Positions')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Radio::make('order_type')
                                ->label('Reorder By')
                                ->options([
                                    'name_asc' => 'Name (A-Z)',
                                    'name_desc' => 'Name (Z-A)',
                                    'price_asc' => 'Price (Low to High)',
                                    'price_desc' => 'Price (High to Low)',
                                    'rating_desc' => 'Rating (High to Low)',
                                    'created_desc' => 'Newest First',
                                    'created_asc' => 'Oldest First',
                                ])
                                ->default('name_asc')
                                ->required(),
                        ])
                        ->action(function ($records, array $data): void {
                            $orderType = $data['order_type'] ?? 'name_asc';
                            $collectionId = $this->ownerRecord->id;

                            $products = $records->sortBy(function ($product) use ($orderType) {
                                return match ($orderType) {
                                    'name_asc' => $product->name,
                                    'name_desc' => $product->name,
                                    'price_asc' => $product->selling_price ?? 0,
                                    'price_desc' => $product->selling_price ?? 0,
                                    'rating_desc' => $product->reviews_avg_rating ?? 0,
                                    'created_desc' => $product->created_at,
                                    'created_asc' => $product->created_at,
                                    default => $product->name,
                                };
                            }, SORT_REGULAR, $orderType === 'name_desc' || $orderType === 'price_desc' || $orderType === 'rating_desc' || $orderType === 'created_desc' ? SORT_DESC : SORT_ASC);

                            foreach ($products as $index => $product) {
                                DB::table('storefront_collection_products')
                                    ->where('storefront_collection_id', $collectionId)
                                    ->where('product_id', $product->id)
                                    ->update(['position' => $index + 1]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Products Reordered')
                                ->body(count($records) . ' products reordered successfully.')
                                ->send();
                        }),
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (! $query) {
            $query = $this->getRelationship()->getQuery();
        }

        return $query
            ->with(['category', 'latestMarginLog'])
            ->withAvg('reviews', 'rating')
            ->addSelect([
                'first_image_url' => DB::table('product_images')
                    ->select('url')
                    ->whereColumn('product_images.product_id', 'products.id')
                    ->orderBy('product_images.position', 'asc')
                    ->limit(1),
                'variants_min_price' => DB::table('product_variants')
                    ->selectRaw('MIN(price)')
                    ->whereColumn('product_variants.product_id', 'products.id'),
                'variants_max_price' => DB::table('product_variants')
                    ->selectRaw('MAX(price)')
                    ->whereColumn('product_variants.product_id', 'products.id'),
            ]);
    }

    private function maybeProxyCjUrl(string $url): string
    {
        if (str_starts_with($url, 'https://cf.cjdropshipping.test/')) {
            return url('/media/proxy?url=' . urlencode($url));
        }

        return $url;
    }
}
