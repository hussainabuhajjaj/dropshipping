<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MobileTranslationResource\Pages;
use App\Models\MobileTranslation;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use UnitEnum;

class MobileTranslationResource extends BaseResource
{
    protected static ?string $model = MobileTranslation::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-language';

    protected static UnitEnum|string|null $navigationGroup = 'Localization';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Mobile Translation')
                ->schema([
                    Forms\Components\Select::make('locale')
                        ->required()
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                        ])
                        ->native(false),
                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->maxLength(255)
                        ->unique(MobileTranslation::class, 'key', ignoreRecord: true, modifyRuleUsing: function ($rule, $get) {
                            return $rule->where('locale', $get('locale'));
                        })
                        ->helperText('Use the exact string key from the app.'),
                    Forms\Components\Textarea::make('value')
                        ->required()
                        ->rows(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('locale')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('key')->sortable()->searchable()->limit(60),
                Tables\Columns\TextColumn::make('value')->wrap()->limit(80),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
               DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                  DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileTranslations::route('/'),
            'create' => Pages\CreateMobileTranslation::route('/create'),
            'edit' => Pages\EditMobileTranslation::route('/{record}/edit'),
        ];
    }
}
