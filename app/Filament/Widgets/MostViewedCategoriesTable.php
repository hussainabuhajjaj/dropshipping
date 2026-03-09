<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\VisitorEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MostViewedCategoriesTable extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return 'category:' . (Arr::get($record, 'entity_id') ?? Arr::get($record, 'slug') ?? md5(json_encode($record)));
        }

        return 'category:' . ($record->getAttribute('entity_id') ?? $record->getAttribute('slug') ?? spl_object_id($record));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most Viewed Categories')
            ->query(
                VisitorEvent::query()
                    ->selectRaw('visitor_events.entity_id')
                    ->selectRaw('COALESCE(MAX(categories.name), MAX(visitor_events.entity_slug), "Category") as category_name')
                    ->selectRaw('MAX(visitor_events.entity_slug) as slug')
                    ->selectRaw('COUNT(*) as views')
                    ->leftJoin('categories', 'categories.id', '=', 'visitor_events.entity_id')
                    ->where('visitor_events.entity_type', 'category')
                    ->groupBy('visitor_events.entity_id')
                    ->orderByDesc('views')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Category'),
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
