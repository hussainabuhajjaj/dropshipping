<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DataImportLogResource\Pages;
use App\Models\DataImportLog;
use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use UnitEnum;

class DataImportLogResource extends BaseResource
{
    protected static ?string $model = DataImportLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 99;
    protected static bool $adminOnly = true;
    protected static bool $staffReadOnly = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Imported')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_rows')
                    ->label('Rows')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_count')
                    ->label('Created')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_count')
                    ->label('Updated')
                    ->sortable(),
                Tables\Columns\TextColumn::make('skipped_count')
                    ->label('Skipped')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Import summary')
                    ->modalContent(fn (DataImportLog $record) => view('filament.imports.details', ['record' => $record])),
            ])
            ->filters([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDataImportLogs::route('/'),
        ];
    }
}
