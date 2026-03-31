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
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PickProducts extends Page
{
    use InteractsWithRecord;
    
    protected static string $resource = StorefrontCollectionResource::class;
    
    protected string $view = 'filament.resources.storefront-collection-resource.pages.pick-products';
    
    protected static bool $shouldRegisterNavigation = false;
    
    protected static ?string $title = 'Pick Products';
    
    protected ?string $heading = 'Pick Products for Collection';
    
    public ?array $selectedProducts = [];
    
    public function mount(int $record): void
    {
        $this->record = StorefrontCollection::findOrFail($record);

        // This picker should only show products that are not already attached.
        $this->selectedProducts = [];
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_collection')
                ->label('Back to Collection')
                ->url(fn () => route('filament.admin.resources.storefront-collections.edit', ['record' => $this->record->id]))
                ->icon('heroicon-o-arrow-left'),
                
            Actions\Action::make('add_selected')
                ->label('Add Selected Products')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->action(function (): void {
                    $this->addSelectedProducts();
                })
                ->disabled(fn () => empty($this->selectedProducts)),
                
            Actions\Action::make('remove_selected')
                ->label('Remove Selected Products')
                ->icon('heroicon-o-minus')
                ->color('danger')
                ->action(function (): void {
                    $this->removeSelectedProducts();
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
                    ->options(function () {
                        // Debug: Log category options loading
                        $categories = Category::query()->orderBy('name')->pluck('name', 'id')->all();
                        \Log::info('PickProducts category options loaded', [
                            'total_categories' => count($categories),
                            'categories' => array_slice($categories, 0, 5, true), // First 5 categories
                            'timestamp' => now()->toISOString()
                        ]);
                        return ['' => 'All Categories'] + $categories;
                    })
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(function (string $search) {
                        // Debug: Log search attempt
                        \Log::info('PickProducts category search', [
                            'search_term' => $search,
                            'timestamp' => now()->toISOString()
                        ]);
                        
                        $results = Category::query()
                            ->where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                      ->orWhere('slug', 'like', "%{$search}%");
                            })
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id');
                            
                        // Debug: Log search results
                        \Log::info('PickProducts category search results', [
                            'search_term' => $search,
                            'results_count' => $results->count(),
                            'results' => $results->take(5)->toArray(), // First 5 results
                            'timestamp' => now()->toISOString()
                        ]);
                        
                        return $results;
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
                        
                        // Debug: Log filter application
                        \Log::info('PickProducts category filter applied', [
                            'category_id' => $value,
                            'table' => $query->getModel()->getTable(),
                            'sql_before' => $query->toSql(),
                            'timestamp' => now()->toISOString()
                        ]);
                        
                        // Handle empty/null values - show all products
                        if (blank($value)) {
                            \Log::info('PickProducts category filter cleared - showing all products', [
                                'timestamp' => now()->toISOString()
                            ]);
                            return $query;
                        }
                        
                        // Explicitly filter by category_id
                        $query = $query->where('products.category_id', $value);
                        
                        // Debug: Log the final query
                        \Log::info('PickProducts category filter query', [
                            'category_id' => $value,
                            'sql_after' => $query->toSql(),
                            'bindings' => $query->getBindings(),
                            'timestamp' => now()->toISOString()
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
            ->actions([
                Tables\Actions\Action::make('toggle_selection')
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
            ->bulkActions([
                Tables\Actions\BulkAction::make('add_to_collection')
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
                    
                Tables\Actions\BulkAction::make('remove_from_collection')
                    ->label('Remove from Collection')
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
    
    protected function addSelectedProducts(): void
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
        
        Notification::make()
            ->success()
            ->title('Products Added')
            ->body(count($newProductIds) . ' products added to collection "' . $this->record->title . '".')
            ->send();
            
        // Refresh the selected products list
        $this->selectedProducts = $this->record->products()
            ->orderBy('storefront_collection_products.position')
            ->pluck('products.id')
            ->toArray();
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
}
