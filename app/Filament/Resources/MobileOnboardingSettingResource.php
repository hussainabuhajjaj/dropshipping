<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MobileOnboardingSettingResource\Pages;
use App\Models\MobileOnboardingSetting;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class MobileOnboardingSettingResource extends BaseResource
{
    protected static ?string $model = MobileOnboardingSetting::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static UnitEnum|string|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 25;

    // protected static bool $adminOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Mobile onboarding')->columnSpan(12)
                ->schema([
                    Forms\Components\Select::make('locale')
                        ->required()
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                        ])
                        ->native(false)
                        ->unique(MobileOnboardingSetting::class, 'locale', ignoreRecord: true),
                    Forms\Components\Toggle::make('enabled')
                        ->label('Enabled')
                        ->default(true),
                    Forms\Components\Repeater::make('slides')->columnSpan(12)
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->required()
                                ->maxLength(50)
                                ->helperText('Unique identifier for this slide (e.g., hello, ready).'),
                            Forms\Components\Select::make('background')
                                ->required()
                                ->options([
                                    'hello' => 'Hello',
                                    'ready' => 'Ready',
                                ])
                                ->native(false),
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(80),
                            Forms\Components\Textarea::make('body')
                                ->rows(3)
                                ->required(),
                            Forms\Components\FileUpload::make('image')
                                ->label('Image')
                                ->disk('public')
                                ->directory('onboarding')
                                ->image()
                                ->helperText('Optional image shown on this slide.'),
                            Forms\Components\ColorPicker::make('image_color_1')
                                ->label('Gradient color #1')
                                ->default('#ffcad9')
                                ->required(),
                            Forms\Components\ColorPicker::make('image_color_2')
                                ->label('Gradient color #2')
                                ->default('#f39db0')
                                ->required(),
                            Forms\Components\TextInput::make('action_href')
                                ->label('Action link (last slide)')
                                ->maxLength(255)
                                ->helperText('Optional link (e.g., /(tabs)/home). Used by the last slide button.'),
                        ])
                        ->columns(2)
                        ->minItems(0)
                        ->reorderable()
                        ->default([]),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('locale')->sortable()->searchable(),
                Tables\Columns\ToggleColumn::make('enabled')->label('Enabled')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileOnboardingSettings::route('/'),
            'create' => Pages\CreateMobileOnboardingSetting::route('/create'),
            'edit' => Pages\EditMobileOnboardingSetting::route('/{record}/edit'),
        ];
    }
}
