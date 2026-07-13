<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerSegmentResource\Pages;
use App\Models\CustomerSegment;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerSegmentResource extends BaseResource
{
    protected static ?string $model = CustomerSegment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 18;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Segment details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(120)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('description')
                        ->rows(2),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Rules')
                ->description('Build conditions to match customers. Use the JSON format below.')
                ->schema([
                    Forms\Components\Textarea::make('rules')
                        ->label('Rule definition (JSON)')
                        ->rows(12)
                        ->monospace()
                        ->helperText(function (): string {
                            return 'Example: {"operator":"and","conditions":[{"field":"total_spent","operator":"gte","value":100},{"group":{"operator":"or","conditions":[{"field":"locale","operator":"eq","value":"fr"},{"field":"locale","operator":"eq","value":"en"}]}}]}';
                        })
                        ->json()
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_count')
                    ->label('Matching')
                    ->state(fn (CustomerSegment $record): string => number_format($record->customerCount()))
                    ->sortable(false),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerSegments::route('/'),
            'create' => Pages\CreateCustomerSegment::route('/create'),
            'edit' => Pages\EditCustomerSegment::route('/{record}/edit'),
        ];
    }
}
