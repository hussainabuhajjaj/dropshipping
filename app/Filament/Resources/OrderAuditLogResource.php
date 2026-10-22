<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Orders\Models\OrderAuditLog;
use App\Filament\Resources\OrderAuditLogResource\Pages;
use BackedEnum;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderAuditLogResource extends BaseResource
{
    protected static ?string $model = OrderAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 30;
    protected static bool $adminOnly = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')->label('Order #')->searchable(),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('note')->limit(60),
                Tables\Columns\TextColumn::make('user.name')->label('By')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}



