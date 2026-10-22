<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EnvChangeLogResource\Pages;
use App\Models\EnvChangeLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class EnvChangeLogResource extends BaseResource
{
    protected static ?string $model = EnvChangeLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 98;
    protected static bool $adminOnly = true;
    protected static bool $staffReadOnly = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Changed')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('changes')
                    ->label('Keys')
                    ->formatStateUsing(fn (array $state): string => implode(', ', array_keys($state)))
                    ->limit(60),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
              Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Environment changes')
                    ->modalContent(fn (EnvChangeLog $record) => view('filament.env.details', ['record' => $record])),
            ])
            ->filters([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnvChangeLogs::route('/'),
        ];
    }
}
