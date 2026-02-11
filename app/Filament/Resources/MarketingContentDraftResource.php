<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MarketingContentDraftResource\Pages;
use App\Models\MarketingContentDraft;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Support\Marketing\MarketingDraftGenerator;

class MarketingContentDraftResource extends BaseResource
{
    protected static ?string $model = MarketingContentDraft::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
           Section::make('Draft')
                ->schema([
                    TextInput::make('target_type')->disabled(),
                    TextInput::make('target_id')->numeric()->disabled(),
                    TextInput::make('locale')->disabled(),
                    TextInput::make('channel')->disabled(),
                    Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'pending_review' => 'Pending Review',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'published' => 'Published',
                        ])
                        ->required(),
                    KeyValue::make('generated_fields')->hiddenLabel()->columnSpanFull()->disabled(),
                    KeyValue::make('prompt_context')->hiddenLabel()->columnSpanFull()->disabled(),
                    Textarea::make('rejected_reason')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('target_type')->badge()->sortable(),
                TextColumn::make('target_id')->sortable(),
                TextColumn::make('locale')->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'warning' => ['draft', 'pending_review'],
                        'info' => 'published',
                    ])
                    ->sortable(),
                TextColumn::make('channel')->sortable(),
                IconColumn::make('approved_by')->boolean()->label('Approved'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_review' => 'Pending Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'published' => 'Published',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                HeaderAction::make('generate_ai')
                    ->label('Generate with AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('target_type')
                            ->label('Marketing section')
                            ->options([
                                'campaign' => 'Campaign',
                                'banner' => 'Banner',
                                'promotion' => 'Promotion',
                                'coupon' => 'Coupon',
                                'newsletter' => 'Newsletter',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('target_id')
                            ->numeric()
                            ->label('Target ID (optional)'),
                    ])
                    ->action(function (array $data): void {
                        $draft = MarketingDraftGenerator::generateDraftFromContext(
                            $data['target_type'] ?? 'campaign',
                            $data['target_id'] ?? null
                        );
                        Notification::make()
                            ->title($draft ? 'AI draft created' : 'Generation failed')
                            ->{$draft ? 'success' : 'danger'}()
                            ->body($draft ? 'Review it in the drafts list.' : 'Check DeepSeek configuration or logs.')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketingContentDrafts::route('/'),
            'create' => Pages\CreateMarketingContentDraft::route('/create'),
            'edit' => Pages\EditMarketingContentDraft::route('/{record}/edit'),
        ];
    }

}
