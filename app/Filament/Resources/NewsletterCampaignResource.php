<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterCampaignResource\Pages;
use App\Filament\Resources\NewsletterCampaignResource\RelationManagers\LogsRelationManager;
use App\Models\NewsletterCampaign;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NewsletterCampaignResource extends BaseResource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 36;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Campaign')
                ->schema([
                    Forms\Components\TextInput::make('subject')->disabled()->columnSpanFull(),
                    Forms\Components\MarkdownEditor::make('body_markdown')
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('action_url')->disabled(),
                    Forms\Components\TextInput::make('action_label')->disabled(),
                ]),
            Section::make('Status')
                ->schema([
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\TextInput::make('total_subscribers')->disabled(),
                    Forms\Components\TextInput::make('sender.name')->label('Sent by')->disabled(),
                    Forms\Components\DateTimePicker::make('sent_at')->disabled(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('total_subscribers')->label('Recipients')->sortable(),
                Tables\Columns\TextColumn::make('sender.name')->label('Sent by')->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'queued' => 'Queued',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'empty' => 'Empty',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterCampaigns::route('/'),
            'view' => Pages\ViewNewsletterCampaign::route('/{record}'),
        ];
    }
}
