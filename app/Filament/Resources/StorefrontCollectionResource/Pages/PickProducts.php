<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCollectionResource\Pages;

use App\Filament\Resources\StorefrontCollectionResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\StorefrontCollection;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PickProducts extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = StorefrontCollectionResource::class;

    protected string $view = 'filament.resources.storefront-collection-resource.pages.pick-products';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Pick Products';

    protected ?string $heading = 'Pick Products for Collection';

    public ?array $selectedProducts = [];

    protected ?Collection $categoryFilterCategories = null;

    public function mount(int|string $record): void
    {
        $this->record = StorefrontCollection::query()
            ->when(
                is_numeric($record),
                fn ($query) => $query->whereKey((int) $record),
                fn ($query) => $query->where('slug', (string) $record)
            )
            ->firstOrFail();

        // This picker should only show products that are not already attached.
        $this->selectedProducts = [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_collection')
                ->label('Back to Collection')
                ->url(fn (): string => StorefrontCollectionResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-arrow-left'),

            Actions\Action::make('add_selected')
                ->label('Add Selected Products')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->action(function (): void {
                    $this->addSelectedProducts();
                })
                ->disabled(fn () => empty($this->selectedProducts)),

            Actions\Action::make('add_and_back')
                ->label('Add and return')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action(function (): void {
                    $this->addSelectedProducts(redirectAfter: true);
                })
                ->disabled(fn () => empty($this->selectedProducts)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with(['category', 'images'])
                    ->whereNotIn('products.id', function ($sub) {
                        $sub->select('product_id')
                            ->from('storefront_collection_products')
                            ->where('storefront_collection_id', $this->record->id);
                    })
            )
            ->columns([
                Tables\Columns\CheckboxColumn::make('selected')
                    ->label('')
                    ->getStateUsing(function (Product $record): bool {
                        return in_array($record->id, $this->selectedProducts);
                    })
                    ->updateStateUsing(function (bool $state, Product $record): void {
                        if ($state) {
                            $this->selectedProducts[] = $record->id;
                            $this->selectedProducts = array_unique($this->selectedProducts);
                        } else {
                            $this->selectedProducts = array_values(array_diff($this->selectedProducts, [$record->id]));
                        }
                    }),

                Tables\Columns\ImageColumn::make('first_image')
                    ->label('')
                    ->square()
                    ->size(40)
                    ->getStateUsing(function (Product $record): ?string {
                        return $record->images->first()?->url;
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Price')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviews_avg_rating')
                    ->label('Rating')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => $this->getCategoryFilterOptions())
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(fn (string $search): array => $this->getCategoryFilterSearchResults($search))
                    ->getOptionLabelUsing(fn ($value): string => $this->getCategoryFilterLabel($value))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereIn('products.category_id', $this->resolveCategoryFilterIds((int) $value));
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

                        if ($min !== null) {
                            $query->where('selling_price', '>=', $min);
                        }
                        if ($max !== null) {
                            $query->where('selling_price', '<=', $max);
                        }

                        return $query;
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
                        if ($rating === null) {
                            return $query;
                        }
                        return $query->having('reviews_avg_rating', '>=', $rating);
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
            ])
            ->recordActions([
                Actions\Action::make('toggle_selection')
                    ->label(fn (Product $record): string =>
                        in_array($record->id, $this->selectedProducts) ? 'Remove' : 'Add'
                    )
                    ->icon(fn (Product $record): string =>
                        in_array($record->id, $this->selectedProducts) ? 'heroicon-o-minus' : 'heroicon-o-plus'
                    )
                    ->color(fn (Product $record): string =>
                        in_array($record->id, $this->selectedProducts) ? 'danger' : 'success'
                    )
                    ->action(function (Product $record): void {
                        if (in_array($record->id, $this->selectedProducts)) {
                            $this->selectedProducts = array_values(array_diff($this->selectedProducts, [$record->id]));
                        } else {
                            $this->selectedProducts[] = $record->id;
                            $this->selectedProducts = array_unique($this->selectedProducts);
                        }
                    }),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('add_to_collection')
                    ->label('Add to Collection')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->action(function (Collection $records): void {
                        $newIds = $records->pluck('id')->diff($this->selectedProducts)->values()->all();
                        $this->selectedProducts = array_unique(array_merge($this->selectedProducts, $newIds));

                        Notification::make()
                            ->success()
                            ->title('Products Added')
                            ->body(count($newIds) . ' products added to selection.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                Actions\BulkAction::make('remove_from_selection')
                    ->label('Remove from Selection')
                    ->icon('heroicon-o-minus')
                    ->color('danger')
                    ->action(function (Collection $records): void {
                        $removedIds = $records->pluck('id')->intersect($this->selectedProducts)->values()->all();
                        $this->selectedProducts = array_values(array_diff($this->selectedProducts, $removedIds));

                        Notification::make()
                            ->success()
                            ->title('Products Removed')
                            ->body(count($removedIds) . ' products removed from selection.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginated([25, 50, 100])
            ->poll('60s');
    }

    protected function getCategoryFilterOptions(): array
    {
        return $this->getCategoryFilterCategories()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $this->formatCategoryFilterLabel($category),
            ])
            ->all();
    }

    protected function getCategoryFilterSearchResults(string $search): array
    {
        return $this->getCategoryFilterCategories()
            ->filter(function (Category $category) use ($search): bool {
                return str_contains(Str::lower($category->name ?? ''), Str::lower($search))
                    || str_contains(Str::lower($category->slug ?? ''), Str::lower($search));
            })
            ->take(50)
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $this->formatCategoryFilterLabel($category),
            ])
            ->all();
    }

    protected function getCategoryFilterLabel(mixed $value): string
    {
        if (blank($value)) {
            return 'All Categories';
        }

        $category = $this->getCategoryFilterCategories()->firstWhere('id', (int) $value);

        return $category
            ? $this->formatCategoryFilterLabel($category)
            : (string) $value;
    }

    protected function formatCategoryFilterLabel(Category $category): string
    {
        $segments = [$category->name];
        $categoriesById = $this->getCategoryFilterCategories()->keyBy('id');
        $current = $categoriesById->get($category->parent_id);

        while ($current) {
            array_unshift($segments, $current->name);
            $current = $categoriesById->get($current->parent_id);
        }

        return Str::limit(implode(' / ', $segments), 120);
    }

    /**
     * @return array<int>
     */
    protected function resolveCategoryFilterIds(int $categoryId): array
    {
        $childrenByParent = $this->getCategoryFilterCategories()
            ->groupBy('parent_id')
            ->map(fn (Collection $group): array => $group->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $ids = [];
        $stack = [$categoryId];

        while ($stack !== []) {
            $currentId = array_pop($stack);

            if (! $currentId || in_array($currentId, $ids, true)) {
                continue;
            }

            $ids[] = $currentId;

            foreach ($childrenByParent->get($currentId, []) as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $stack[] = $childId;
                }
            }
        }

        return $ids;
    }

    protected function getCategoryFilterCategories(): Collection
    {
        if ($this->categoryFilterCategories instanceof Collection) {
            return $this->categoryFilterCategories;
        }

        return $this->categoryFilterCategories = Category::query()
            ->select(['id', 'name', 'slug', 'parent_id'])
            ->orderBy('name')
            ->get();
    }

    public function addSelectedProducts(bool $redirectAfter = false): void
    {
        if (empty($this->selectedProducts)) {
            return;
        }

        $currentProductIds = $this->record->products()->pluck('products.id')->toArray();
        $newProductIds = array_diff($this->selectedProducts, $currentProductIds);

        if (empty($newProductIds)) {
            Notification::make()
                ->warning()
                ->title('No New Products')
                ->body('All selected products are already in this collection.')
                ->send();
            return;
        }

        // Add new products with position
        $maxPosition = $this->record->products()->max('storefront_collection_products.position') ?? 0;

        $syncData = [];
        foreach ($newProductIds as $index => $productId) {
            $syncData[$productId] = [
                'position' => $maxPosition + $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->record->products()->syncWithoutDetaching($syncData);

        if (($this->record->selection_mode ?? 'rules') === 'rules') {
            $this->record->update(['selection_mode' => 'hybrid']);
            $this->record->refresh();
        }

        Notification::make()
            ->success()
            ->title('Products Added')
            ->body(
                count($newProductIds) . ' products added to collection "' . $this->record->title . '".'
                . (($this->record->selection_mode ?? null) === 'hybrid'
                    ? ' Selection mode is now Hybrid so attached products and rules work together.'
                    : '')
            )
            ->send();

        $this->selectedProducts = [];

        if ($redirectAfter) {
            $this->redirect(StorefrontCollectionResource::getUrl('edit', ['record' => $this->record]));
        }
    }

    protected function removeSelectedProducts(): void
    {
        if (empty($this->selectedProducts)) {
            return;
        }

        $currentProductIds = $this->record->products()->pluck('products.id')->toArray();
        $productsToRemove = array_intersect($this->selectedProducts, $currentProductIds);

        if (empty($productsToRemove)) {
            Notification::make()
                ->warning()
                ->title('No Products to Remove')
                ->body('None of the selected products are in this.')
                ->send();
            return;
        }

        $this->record->products()->detach($productsToRemove);

        Notification::make()
            ->success()
            ->title('Products Removed')
            ->body(count($productsToRemove) . ' products removed from collection "' . $this->record->title . '".')
            ->send();

        // Refresh the selected products list
        $this->selectedProducts = $this->record->products()
            ->orderBy('storefront_collection_products.position')
            ->pluck('products.id')
            ->toArray();
    }

    public function getTitle(): string
    {
        return 'Pick Products for: ' . $this->record->title;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here to show collection stats
        ];
    }

    public function getAttachedProductsCountProperty(): int
    {
        return (int) $this->record->products()->count();
    }

    public function getSelectedProductsCountProperty(): int
    {
        return count($this->selectedProducts ?? []);
    }

    public function getAvailableProductsCountProperty(): int
    {
        return (int) Product::query()
            ->whereNotIn('products.id', function ($sub) {
                $sub->select('product_id')
                    ->from('storefront_collection_products')
                    ->where('storefront_collection_id', $this->record->id);
            })
            ->count();
    }
}
