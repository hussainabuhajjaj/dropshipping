<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\VisitorEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MostViewedPagesTable extends BaseWidget
{
    protected static ?int $sort = 11;

    protected int | string | array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return 'page:' . (Arr::get($record, 'page_key') ?? Arr::get($record, 'path') ?? md5(json_encode($record)));
        }

        return 'page:' . ($record->getAttribute('page_key') ?? $record->getAttribute('path') ?? spl_object_id($record));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most Viewed Pages')
            ->query(
                VisitorEvent::query()
                    ->selectRaw('COALESCE(MAX(page_key), MAX(path)) as page_key')
                    ->selectRaw('MAX(path) as path')
                    ->selectRaw('COUNT(*) as views')
                    ->where('entity_type', 'page')
                    ->groupByRaw('COALESCE(page_key, path)')
                    ->orderByDesc('views')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('page_key')
                    ->label('Page'),
                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('views')
                    ->label('Views')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('views', 'desc');
    }
}
