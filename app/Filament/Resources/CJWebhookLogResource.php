<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CJWebhookLogResource\Pages;
use App\Models\CJWebhookLog;
use App\Filament\Resources\BaseResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use BackedEnum;
use Filament\Actions\ViewAction;
use UnitEnum;

class CJWebhookLogResource extends BaseResource
{
    protected static ?string $model = CJWebhookLog::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';

    protected static UnitEnum|string|null $navigationGroup = 'Integrations';
    protected static bool $staffReadOnly = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('message_id')->label('Message ID')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('message_type')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        Forms\Components\ViewField::make('payload')
                            ->view('filament.resources.cj-webhook-log.payload'),
                    ]),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCJWebhookLogs::route('/'),
            'view' => Pages\ViewCJWebhookLog::route('/{record}'),
        ];
    }
}

