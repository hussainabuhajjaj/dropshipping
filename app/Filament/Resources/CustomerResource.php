<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\PaymentMethodsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\SupportConversationsRelationManager;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class CustomerResource extends BaseResource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 12;
    protected static ?string $recordTitleAttribute = 'email';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['orders', 'addresses', 'supportConversations'])
            ->withSum('orders as orders_total_spent', 'grand_total');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Customer Profile')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('last_name')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->maxLength(190)
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(30),
                    Forms\Components\TextInput::make('locale')
                        ->maxLength(5)
                        ->placeholder('en'),
                    Forms\Components\TextInput::make('country_code')
                        ->maxLength(2)
                        ->default('CI'),
                ])
                ->columns(3),

            Section::make('Address')
                ->schema([
                    Forms\Components\TextInput::make('city')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('region')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('postal_code')
                        ->maxLength(30),
                    Forms\Components\TextInput::make('address_line1')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('address_line2')
                        ->maxLength(255),
                ])
                ->columns(2),

            Section::make('Security & Verification')
                ->schema([
                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('phone_verified_at')
                        ->seconds(false),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Leave empty to keep the current password.'),
                ])
                ->columns(3),

            Section::make('Metadata')
                ->schema([
                    Forms\Components\KeyValue::make('metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Email verified')
                    ->boolean(),
                Tables\Columns\IconColumn::make('phone_verified_at')
                    ->label('Phone verified')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_total_spent')
                    ->label('Total spent')
                    ->state(fn (Customer $record): string => number_format((float) ($record->orders_total_spent ?? 0), 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options(fn (): array => Customer::query()
                        ->whereNotNull('locale')
                        ->distinct()
                        ->orderBy('locale')
                        ->pluck('locale', 'locale')
                        ->toArray()),
                TernaryFilter::make('email_verified')
                    ->label('Email verified')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    ),
                TernaryFilter::make('phone_verified')
                    ->label('Phone verified')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('phone_verified_at'),
                    ),
                Filter::make('has_orders')
                    ->label('Has orders')
                    ->query(fn (Builder $query): Builder => $query->has('orders')),
                Filter::make('recent')
                    ->label('Joined last 30 days')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(30))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Customer')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('name')->label('Full name'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('locale')->placeholder('—'),
                    TextEntry::make('country_code')->placeholder('—'),
                    IconEntry::make('email_verified_at')->label('Email verified')->boolean(),
                    IconEntry::make('phone_verified_at')->label('Phone verified')->boolean(),
                    TextEntry::make('created_at')->label('Joined')->dateTime(),
                ])
                ->columns(3),

            Section::make('Order Metrics')
                ->schema([
                    TextEntry::make('orders_count')
                        ->label('Orders')
                        ->state(fn (Customer $record): int => (int) ($record->orders_count ?? $record->orders()->count())),
                    TextEntry::make('orders_total_spent')
                        ->label('Total spent')
                        ->state(fn (Customer $record): string => number_format((float) ($record->orders_total_spent ?? $record->orders()->sum('grand_total')), 2)),
                    TextEntry::make('support_conversations_count')
                        ->label('Support chats')
                        ->state(fn (Customer $record): int => (int) ($record->support_conversations_count ?? $record->supportConversations()->count())),
                ])
                ->columns(3),

            Section::make('Primary Address')
                ->schema([
                    TextEntry::make('address_line1')->placeholder('—'),
                    TextEntry::make('address_line2')->placeholder('—'),
                    TextEntry::make('city')->placeholder('—'),
                    TextEntry::make('region')->placeholder('—'),
                    TextEntry::make('postal_code')->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            AddressesRelationManager::class,
            PaymentMethodsRelationManager::class,
            SupportConversationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
