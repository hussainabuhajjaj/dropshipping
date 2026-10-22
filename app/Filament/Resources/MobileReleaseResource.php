<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MobileReleaseResource\Pages;
use App\Models\MobileRelease;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class MobileReleaseResource extends BaseResource
{
    protected static ?string $model = MobileRelease::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-phone-arrow-up-right';

    protected static UnitEnum|string|null $navigationGroup = 'Storefront';

    protected static ?int $navigationSort = 27;

    protected static bool $adminOnly = true;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Release details')
                ->schema([
                    Forms\Components\TextInput::make('version')
                        ->label('Version')
                        ->required()
                        ->maxLength(50)
                        ->placeholder('e.g. 1.0.12'),
                    Forms\Components\Select::make('platform')
                        ->options([
                            'android' => 'Android',
                            'ios' => 'iOS',
                        ])
                        ->default('android')
                        ->required()
                        ->native(false),
                    Forms\Components\FileUpload::make('file_path')
                        ->label('APK file')
                        ->disk('public')
                        ->directory('releases/apks')
                        ->maxSize(1000 * 1024 * 1024)
                        ->helperText('Upload the APK file (max 250MB).'),
                    Forms\Components\Textarea::make('release_notes')
                        ->label('Release notes')
                        ->rows(5)
                        ->nullable()
                        ->helperText('Describe what changed in this release.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024 / 1024, 1) . ' MB' : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('release_notes')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'android' => 'Android',
                        'ios' => 'iOS',
                    ]),
            ])
            ->recordActions([
               EditAction::make(),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (MobileRelease $record): ?string => $record->url(), shouldOpenInNewTab: true),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileReleases::route('/'),
            'create' => Pages\CreateMobileRelease::route('/create'),
            'edit' => Pages\EditMobileRelease::route('/{record}/edit'),
        ];
    }
}
