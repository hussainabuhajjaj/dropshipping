<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Models\ProductMarginLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MarginLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'marginLogs';
    protected static ?string $recordTitleAttribute = 'event';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event')->sortable(),
                Tables\Columns\TextColumn::make('source')->sortable(),
                Tables\Columns\TextColumn::make('actor_type')->label('Actor')->sortable(),
                Tables\Columns\TextColumn::make('actor_id')->label('Actor ID'),
                Tables\Columns\TextColumn::make('old_margin_percent')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('new_margin_percent')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('old_selling_price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('new_selling_price')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('sales_count')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')->options(fn () => ProductMarginLog::query()->distinct()->pluck('event', 'event')->toArray()),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
