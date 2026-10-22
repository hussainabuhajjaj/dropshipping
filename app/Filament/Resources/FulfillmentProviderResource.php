<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Fulfillment\Models\FulfillmentProvider;
use App\Filament\Resources\FulfillmentProviderResource\Pages;
use BackedEnum;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class FulfillmentProviderResource extends BaseResource
{
    protected static ?string $model = FulfillmentProvider::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Fulfillment';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Provider')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('driver_class')
                        ->helperText('Fully qualified strategy class (e.g., App\\Domain\\Fulfillment\\Strategies\\AliExpressFulfillmentStrategy)')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\TextInput::make('retry_limit')->numeric()->default(3),
                ])->columns(2),
         Section::make('Contact')
                ->schema([
                    Forms\Components\TextInput::make('contact_email')->email(),
                    Forms\Components\TextInput::make('contact_phone'),
                    Forms\Components\TextInput::make('website_url')->url(),
                ])->columns(3),
            Section::make('Integration')
                ->schema([
                    Forms\Components\KeyValue::make('credentials')->keyLabel('Key')->valueLabel('Value'),
                    Forms\Components\KeyValue::make('settings')->keyLabel('Setting')->valueLabel('Value'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('code')->badge()->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('driver_class')->limit(30)->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('retry_limit'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                ActionsEditAction::make(),
            ])
            ->toolbarActions([
                 ActionsBulkActionGroup::make([
                   ActionsDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFulfillmentProviders::route('/'),
            'create' => Pages\CreateFulfillmentProvider::route('/create'),
            'edit' => Pages\EditFulfillmentProvider::route('/{record}/edit'),
        ];
    }
}



