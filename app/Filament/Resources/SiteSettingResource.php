<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SiteSettingResource\Pages;
use App\Models\SiteSetting;
use App\Domain\Fulfillment\Models\FulfillmentProvider;
use BackedEnum;
use UnitEnum;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SiteSettingResource extends BaseResource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 20;
    protected static bool $adminOnly = true;
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Contact')
                ->schema([
                    Forms\Components\TextInput::make('support_email')->email(),
                    Forms\Components\TextInput::make('support_whatsapp'),
                    Forms\Components\TextInput::make('support_phone'),
                    Forms\Components\TextInput::make('support_hours')
                        ->helperText('Example: Mon-Sat, 9:00-18:00 GMT'),
                ])->columns(2),
            Section::make('Storefront')
                ->schema([
                    Forms\Components\TextInput::make('site_name')->label('Site name')->maxLength(120),
                    Forms\Components\Textarea::make('site_description')->label('Site description')->rows(2),
                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Logo')
                        ->disk('public')
                        ->directory('assets/logos')
                        ->image()
                        ->imageEditor()
                        ->maxSize(5120)  // 5MB
                        ->helperText('Recommended: PNG or SVG, 200x50px or larger'),
                    Forms\Components\FileUpload::make('favicon_path')
                        ->label('Favicon')
                        ->disk('public')
                        ->directory('assets/favicons')
                        ->image()
                        ->maxSize(512)  // 512KB
                        ->helperText('Recommended: ICO or PNG, 32x32px or 64x64px'),
                ])->columns(2),
            Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')->label('Meta title')->maxLength(120),
                    Forms\Components\Textarea::make('meta_description')->label('Meta description')->rows(2),
                    Forms\Components\Textarea::make('meta_keywords')->label('Meta keywords')->rows(2),
                ])->columns(2),
            Section::make('Localization')
                ->schema([
                    Forms\Components\TextInput::make('timezone')
                        ->label('Timezone')
                        ->helperText('Example: UTC, Africa/Abidjan, America/New_York'),
                ]),
            Section::make('Theme')
                ->schema([
                    ColorPicker::make('primary_color')->label('Primary color'),
                    ColorPicker::make('secondary_color')->label('Secondary color'),
                    ColorPicker::make('accent_color')->label('Accent color'),
                ])->columns(3),
            Section::make('Shipping & Customs')
                ->schema([
                    Forms\Components\TextInput::make('delivery_window')
                        ->helperText('Example: 7–18 business days'),
                    Forms\Components\Textarea::make('shipping_message')->rows(3),
                    Forms\Components\Textarea::make('customs_message')->rows(3),
                ])->columns(2),
            Section::make('Taxes')
                ->schema([
                    Forms\Components\TextInput::make('tax_label')
                        ->label('Tax label')
                        ->helperText('Example: VAT, Sales Tax')
                        ->maxLength(60),
                    Forms\Components\TextInput::make('tax_rate')
                        ->label('Tax rate (%)')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('%'),
                    Forms\Components\Toggle::make('tax_included')
                        ->label('Prices include tax'),
                ])->columns(3),
            Section::make('Shipping rules')
                ->schema([
                    Forms\Components\TextInput::make('shipping_handling_fee')
                        ->label('Handling fee')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Applied to the quoted shipping total.'),
                    Forms\Components\TextInput::make('free_shipping_threshold')
                        ->label('Free shipping threshold')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Subtotal after discounts that waives shipping.'),
                ])->columns(2),
            Section::make('Policies')
                ->schema([
                    Forms\Components\Textarea::make('shipping_policy')
                        ->label('Shipping policy')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('refund_policy')
                        ->label('Refund policy')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('privacy_policy')
                        ->label('Privacy policy')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('terms_of_service')
                        ->label('Terms of service')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('customs_disclaimer')
                        ->label('Customs disclaimer')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('about_page_html')
                        ->label('About page content')
                        ->helperText('Admin-managed content for the About Us page. Supports basic HTML.')
                        ->rows(6)
                        ->columnSpanFull(),
                ]),
            Section::make('Reviews')
                ->schema([
                    Forms\Components\Toggle::make('auto_approve_reviews')
                        ->label('Auto-approve reviews')
                        ->helperText('Immediately approve verified buyer reviews.'),
                    Forms\Components\TextInput::make('auto_approve_review_days')
                        ->label('Auto-approve after (days)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Pending reviews older than this are auto-approved. 0 disables.'),
                ])->columns(2),
            Section::make('Fulfillment')
                ->schema([
                    Forms\Components\Select::make('default_fulfillment_provider_id')
                        ->label('Default fulfillment provider')
                        ->options(fn () => FulfillmentProvider::query()->where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('cj_auto_approve_delay_hours')
                        ->label('CJ Auto-Approve Delay (hours)')
                        ->numeric()
                        ->minValue(1)
                        ->default(24)
                        ->helperText('Unapproved CJ fulfillment items will be auto-approved after this many hours.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('site_name')->label('Site'),
                Tables\Columns\TextColumn::make('support_email')->label('Email'),
                Tables\Columns\TextColumn::make('support_whatsapp')->label('WhatsApp'),
                Tables\Columns\TextColumn::make('delivery_window')->label('Delivery window'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteSettings::route('/'),
            'edit' => Pages\EditSiteSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return SiteSetting::query()->count() === 0;
    }
}

