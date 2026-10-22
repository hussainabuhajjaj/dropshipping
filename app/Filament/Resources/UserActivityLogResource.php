<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserActivityLogResource\Pages;
use App\Models\UserActivityLog;
use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class UserActivityLogResource extends BaseResource
{
    protected static ?string $model = UserActivityLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static UnitEnum|string|null $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'User Activity Logs';
    protected static ?int $navigationSort = 99;
    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Guest'),
                
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => 
                        $state ? class_basename($state) : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserActivityLogs::route('/'),
        ];
    }
}
