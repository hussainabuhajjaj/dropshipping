<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\SupportConversationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SupportConversationsRelationManager extends RelationManager
{
    protected static string $relationship = 'supportConversations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Conversation')
                    ->searchable()
                    ->url(fn ($record) => SupportConversationResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_agent')
                    ->label('Agent')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'human' ? 'Human' : 'AI'),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
