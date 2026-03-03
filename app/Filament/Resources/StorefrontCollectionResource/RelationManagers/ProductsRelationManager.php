<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCollectionResource\RelationManagers;

use App\Models\Category;
use App\Services\Storefront\HomeBuilderService;
use App\Jobs\ApplyProductMarginChunkJob;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\BulkAction;
use Filament\Forms;
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
                    ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
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
                    }),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([
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
                        }),
                    BulkAction::make('feature')
                        ->label('Mark featured')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $ids = $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            if ($ids === []) {
                                return;
                            }

                            Product::query()->whereIn('id', $ids)->update([
                                'is_featured' => true,
                            ]);
                        }),
                    BulkAction::make('apply_margin')
                        ->label('Apply margin')
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
        if (str_starts_with($url, 'https://cf.cjdropshipping.com/')) {
            return url('/media/proxy?url=' . urlencode($url));
        }

        return $url;
    }
}
