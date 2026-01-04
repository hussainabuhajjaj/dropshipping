<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Domain\Payments\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section as ComponentsSection;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge(),

                TextColumn::make('amount')
                    ->money(fn(Payment $record) => $record->currency)
                    ->sortable(),

                BadgeColumn::make('status')
                    ->color(fn(?string $state) => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('created_at')
                    ->label('Initiated')
                    ->dateTime('M d, Y H:i'),

                TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime('M d, Y H:i'),
            ])
            ->filters([
                //...existing code...
            ])
            ->headerActions([
                //...existing code...
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                //...existing code...
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            ComponentsSection::make('Payment Info')
                ->schema([
                    TextEntry::make('provider'),
                    TextEntry::make('amount'),
                    TextEntry::make('currency'),
                    TextEntry::make('status'),
                    TextEntry::make('reference')
                        ->copyable(),
                ])
                ->columns(3),

            ComponentsSection::make('Gateway Response')
                ->schema([
                    TextEntry::make('gateway_response')
                        ->state(fn(Payment $record) => json_encode($record->gateway_response, JSON_PRETTY_PRINT)),
                ])
                ->columnSpanFull()
                ->collapsed()
                ->visible(fn(Payment $record) => $record->gateway_response !== null),
        ]);
    }
}
