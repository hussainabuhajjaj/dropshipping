<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\StorefrontSettingResource\Pages;
use App\Models\StorefrontSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StorefrontSettingResource extends BaseResource
{
    protected static ?string $model = StorefrontSetting::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Storefront';
    protected static bool $adminOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Branding')
                ->schema([
                    Forms\Components\TextInput::make('brand_name')->maxLength(120),
                    Forms\Components\Textarea::make('footer_blurb')->rows(3)->maxLength(500),
                    Forms\Components\TextInput::make('delivery_notice')->maxLength(255),
                    Forms\Components\TextInput::make('copyright_text')->maxLength(255),
                ])
                ->columns(2),
            Section::make('Header links')
                ->schema([
                    Forms\Components\Repeater::make('header_links')
                        ->schema([
                            Forms\Components\TextInput::make('label')->required(),
                            Forms\Components\TextInput::make('href')->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Footer columns')
                ->schema([
                    Forms\Components\Repeater::make('footer_columns')
                        ->schema([
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\Repeater::make('links')
                                ->schema([
                                    Forms\Components\TextInput::make('label')->required(),
                                    Forms\Components\TextInput::make('href')->required(),
                                ])
                                ->columns(2)
                                ->minItems(1)
                                ->reorderable(),
                        ])
                        ->columns(1)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Value props')
                ->schema([
                    Forms\Components\Repeater::make('value_props')
                        ->schema([
                            Forms\Components\TextInput::make('title')->required(),
                            Forms\Components\Textarea::make('body')->rows(3)->required(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->reorderable(),
                ]),
            Section::make('Social links')
                ->schema([
                    Forms\Components\Repeater::make('social_links')
                        ->schema([
                            Forms\Components\TextInput::make('label')->required(),
                            Forms\Components\TextInput::make('href')->required(),
                            Forms\Components\TextInput::make('icon')->helperText('Optional icon name'),
                        ])
                        ->columns(3)
                        ->reorderable(),
                ]),
            Section::make('Coming soon')
                ->schema([
                    Forms\Components\Toggle::make('coming_soon_enabled')
                        ->label('Enable coming soon page'),
                    Forms\Components\TextInput::make('coming_soon_title')
                        ->maxLength(160)
                        ->placeholder('We are launching soon'),
                    Forms\Components\Textarea::make('coming_soon_message')
                        ->rows(3)
                        ->placeholder('Leave your email and get notified when we open.'),
                    Forms\Components\TextInput::make('coming_soon_image')
                        ->helperText('Optional image URL or storage path'),
                    Forms\Components\TextInput::make('coming_soon_cta_label')
                        ->maxLength(80)
                        ->placeholder('Notify me'),
                    Forms\Components\TextInput::make('coming_soon_cta_url')
                        ->placeholder('/'),
                ])
                ->columns(2),
            Section::make('Newsletter popup')
                ->schema([
                    Forms\Components\Toggle::make('newsletter_popup_enabled')
                        ->label('Enable newsletter popup'),
                    Forms\Components\TextInput::make('newsletter_popup_title')
                        ->maxLength(160)
                        ->placeholder('Join the list'),
                    Forms\Components\Textarea::make('newsletter_popup_body')
                        ->rows(3)
                        ->placeholder('Get drop alerts, coupons, and shipping updates.'),
                    Forms\Components\TextInput::make('newsletter_popup_incentive')
                        ->maxLength(160)
                        ->placeholder('Save 10% on your first order'),
                    Forms\Components\TextInput::make('newsletter_popup_image')
                        ->helperText('Optional image URL or storage path'),
                    Forms\Components\TextInput::make('newsletter_popup_delay_seconds')
                        ->numeric()
                        ->minValue(0)
                        ->default(3),
                    Forms\Components\TextInput::make('newsletter_popup_dismiss_days')
                        ->numeric()
                        ->minValue(1)
                        ->default(14),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('brand_name'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStorefrontSettings::route('/'),
            'create' => Pages\CreateStorefrontSetting::route('/create'),
            'edit' => Pages\EditStorefrontSetting::route('/{record}/edit'),
        ];
    }
}
