<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\HomePageSettingResource\Pages;
use App\Models\HomePageSetting;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Forms;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;

class HomePageSettingResource extends BaseResource
{
    protected static ?string $model = HomePageSetting::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'Storefront';
    protected static bool $adminOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Locale')
                ->schema([
                    Forms\Components\Select::make('locale')
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                        ])
                        ->native(false)
                        ->nullable()
                        ->placeholder('Default')
                        ->helperText('Leave empty to use this record as the default for all locales.'),
                ]),
            Section::make('Top strip')
                ->schema([
                    Forms\Components\Repeater::make('top_strip')
                        ->schema([
                            Forms\Components\TextInput::make('icon')->maxLength(4)->default('⚡'),
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\TextInput::make('subtitle')->required(),
                        ])
                        ->columns(3)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Hero slides')
                ->schema([
                    Forms\Components\Repeater::make('hero_slides')
                        ->schema([
                            Forms\Components\TextInput::make('kicker')->required(),
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\Textarea::make('subtitle')->rows(2)->required(),
                            Forms\Components\FileUpload::make('image')
                                ->label('Image')
                                ->disk('public')
                                ->directory('home')
                                ->image()
                                ->required(),
                            Forms\Components\TextInput::make('primary_label')->default('Shop now'),
                            Forms\Components\TextInput::make('primary_href')->default('/products'),
                            Forms\Components\TextInput::make('secondary_label')->default('Track order'),
                            Forms\Components\TextInput::make('secondary_href')->default('/orders/track'),
                            Forms\Components\TagsInput::make('meta')->separator(','),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Hero rail cards')
                ->schema([
                    Forms\Components\Repeater::make('rail_cards')
                        ->schema([
                            Forms\Components\TextInput::make('kicker')->required(),
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\Textarea::make('subtitle')->rows(2)->required(),
                            Forms\Components\TextInput::make('cta')->label('CTA label')->required(),
                            Forms\Components\TextInput::make('href')->label('CTA link')->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Category highlights')
                ->schema([
                    Forms\Components\Repeater::make('category_highlights')
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->columns(1)
                        ->reorderable(),
                ]),
            Section::make('Banner strip')
                ->schema([
                    Forms\Components\TextInput::make('banner_strip.kicker')->required(),
                    Forms\Components\TextInput::make('banner_strip.title')->required(),
                    Forms\Components\TextInput::make('banner_strip.cta')->label('CTA label')->required(),
                    Forms\Components\TextInput::make('banner_strip.href')->label('CTA link')->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'Default')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->recordActions([
                ActionsEditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url('/')
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomePageSettings::route('/'),
            'create' => Pages\CreateHomePageSetting::route('/create'),
            'edit' => Pages\EditHomePageSetting::route('/{record}/edit'),
        ];
    }
}

