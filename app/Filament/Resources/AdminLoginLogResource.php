<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminLoginLogResource\Pages;
use App\Models\AdminLoginLog;
use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class AdminLoginLogResource extends BaseResource
{
    protected static ?string $model = AdminLoginLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-finger-print';
    protected static UnitEnum|string|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 70;
    protected static bool $adminOnly = true;
    protected static bool $staffReadOnly = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('logged_in_at')
                    ->label('Logged in')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Admin')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User agent')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminLoginLogs::route('/'),
        ];
    }
}
