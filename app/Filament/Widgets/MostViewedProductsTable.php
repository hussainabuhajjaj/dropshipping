<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\VisitorEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MostViewedProductsTable extends BaseWidget
{
    protected static ?int $sort = 9;

    protected int | string | array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return 'product:' . (Arr::get($record, 'entity_id') ?? Arr::get($record, 'slug') ?? md5(json_encode($record)));
        }

        return 'product:' . ($record->getAttribute('entity_id') ?? $record->getAttribute('slug') ?? spl_object_id($record));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most Viewed Products')
            ->query(
                VisitorEvent::query()
                    ->selectRaw('visitor_events.entity_id')
                    ->selectRaw('COALESCE(MAX(products.name), MAX(visitor_events.entity_slug), "Product") as product_name')
                    ->selectRaw('MAX(visitor_events.entity_slug) as slug')
                    ->selectRaw('COUNT(*) as views')
                    ->leftJoin('products', 'products.id', '=', 'visitor_events.entity_id')
                    ->where('visitor_events.entity_type', 'product')
                    ->groupBy('visitor_events.entity_id')
                    ->orderByDesc('views')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('views')
                    ->label('Views')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('views', 'desc');
    }
}
