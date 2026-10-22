<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderAuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')->label('Action')->sortable(),
                Tables\Columns\TextColumn::make('note')->limit(60),
                Tables\Columns\TextColumn::make('user.name')->label('By'),
                Tables\Columns\TextColumn::make('created_at')->label('At')->dateTime()->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
