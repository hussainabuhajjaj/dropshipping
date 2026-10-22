<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return 'Transactions';
    }
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('amount')->money(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ]);
    }
}
