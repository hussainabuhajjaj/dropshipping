<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductMarginLogResource\Pages;
use App\Models\ProductMarginLog;
use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductMarginLogResource extends BaseResource
{
    protected static ?string $model = ProductMarginLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Logs')->schema([]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('variant.title')->label('Variant')->sortable()->toggleable(),
                Tables\Columns\BadgeColumn::make('event')->label('Event')->sortable(),
                Tables\Columns\TextColumn::make('source')->sortable(),
                Tables\Columns\TextColumn::make('actor_display')->label('Actor')->formatStateUsing(fn (ProductMarginLog $record) => $record->actor_type . ($record->actor_id ? " #{$record->actor_id}" : ''))->sortable(),
                Tables\Columns\TextColumn::make('old_margin_percent')->label('Old margin')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('new_margin_percent')->label('New margin')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('old_selling_price')->label('Old price')->money('USD'),
                Tables\Columns\TextColumn::make('new_selling_price')->label('New price')->money('USD'),
                Tables\Columns\TextColumn::make('sales_count')->label('Sales')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')->options(fn () => ProductMarginLog::query()->distinct()->pluck('event', 'event')->toArray()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductMarginLogs::route('/'),
        ];
    }
}
