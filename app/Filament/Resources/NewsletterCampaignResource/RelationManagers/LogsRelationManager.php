<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsletterCampaignResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('opened_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('clicked_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('click_count')->numeric()->toggleable(),
                Tables\Columns\TextColumn::make('error_message')->limit(50)->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'queued' => 'Queued',
                    'sent' => 'Sent',
                    'failed' => 'Failed',
                    'unsubscribed' => 'Unsubscribed',
                ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
