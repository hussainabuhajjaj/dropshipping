<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use App\Services\AI\ContentTranslationService;
use App\Services\NewsletterCampaignRenderer;
use App\Services\NewsletterCampaignService;
use BackedEnum;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkAction as ActionsBulkAction;
use Filament\Actions\BulkActionGroup as ActionsBulkActionGroup;
use Filament\Actions\DeleteBulkAction as ActionsDeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as SchemaGet;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscriberResource extends BaseResource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 35;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Subscriber')
                ->schema([
                    Forms\Components\TextInput::make('email')->disabled(),
                    Forms\Components\TextInput::make('source')->disabled(),
                    Forms\Components\TextInput::make('locale')->disabled(),
                    Forms\Components\TextInput::make('ip_address')->label('IP')->disabled(),
                    Forms\Components\Textarea::make('user_agent')
                        ->disabled()
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('meta')
                        ->disabled()
                        ->rows(6)
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('locale')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('unsubscribed_at')
                    ->label('Unsubscribed')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->color(fn ($state) => $state ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Subscribed')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('recent')
                    ->label('Subscribed last 30 days')
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30))),
                Tables\Filters\TernaryFilter::make('unsubscribed')
                    ->label('Unsubscribed')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('unsubscribed_at'),
                        false: fn (Builder $query) => $query->whereNull('unsubscribed_at'),
                    ),
                Tables\Filters\SelectFilter::make('source')
                    ->options(fn () => NewsletterSubscriber::query()
                        ->whereNotNull('source')
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source', 'source')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('locale')
                    ->options(fn () => NewsletterSubscriber::query()
                        ->whereNotNull('locale')
                        ->distinct()
                        ->orderBy('locale')
                        ->pluck('locale', 'locale')
                        ->toArray()),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                ActionsAction::make('send_campaign')
                    ->label('Send campaign')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(200)
                            ->default('Latest offers from ' . config('app.name'))
                            ->live(),
                        Forms\Components\MarkdownEditor::make('body_markdown')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'table',
                            ])
                            ->helperText('Markdown supported. Use {{product:ID}} or {{category:ID}} to embed items. Images: ![](https://url).')
                            ->columnSpanFull()
                            ->default('New promotions are live now. Shop the latest deals and limited-time offers.')
                            ->live(),
                        Forms\Components\TextInput::make('action_url')
                            ->label('Action URL')
                            ->url()
                            ->default(rtrim((string) config('app.url'), '/') . '/promotions')
                            ->live(),
                        Forms\Components\TextInput::make('action_label')
                            ->label('Action label')
                            ->maxLength(80)
                            ->default('Shop promotions')
                            ->live(),
                        Forms\Components\Select::make('recipient_locale')
                            ->label('Recipients')
                            ->options([
                                'all' => 'All subscribers',
                                'en' => 'English + unknown locale',
                                'fr' => 'French only',
                            ])
                            ->native(false)
                            ->default('all')
                            ->helperText('If you have subscriber locales, you can target EN/FR audiences.'),
                        Forms\Components\Toggle::make('translate_to_fr')
                            ->label('Translate EN → FR for French recipients')
                            ->helperText('If Recipients = All, sends EN to non-FR and FR to FR (two campaigns).')
                            ->default(false)
                            ->visible(fn (callable $get) => (string) ($get('recipient_locale') ?? 'all') !== 'en'),
                        Forms\Components\TextInput::make('limit')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Optional: limit number of subscribers'),
                        Forms\Components\Toggle::make('dry_run')
                            ->label('Dry run')
                            ->default(false),
                        Forms\Components\Toggle::make('send_now')
                            ->label('Send immediately (no queue)')
                            ->helperText('Enable to send right away without a queue worker.')
                            ->default(true),
                        Section::make('Preview')
                            ->schema([
                                Forms\Components\ViewField::make('preview')
                                    ->view('filament.newsletter.preview')
                                    ->live()
                                    ->viewData(fn (SchemaGet $get) => [
                                        'preview_html' => app(NewsletterCampaignRenderer::class)->renderPreviewHtml(
                                            (string) ($get('subject') ?? 'Newsletter preview'),
                                            (string) ($get('body_markdown') ?? ''),
                                            $get('action_url'),
                                            $get('action_label')
                                        ),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->collapsed()
                            ->collapsible(),
                    ])
                    ->action(function (array $data): void {
                        $recipientLocale = is_string($data['recipient_locale'] ?? null) ? (string) $data['recipient_locale'] : 'all';
                        $translateToFr = (bool) ($data['translate_to_fr'] ?? false);
                        $sendNow = (bool) ($data['send_now'] ?? true);

                        $query = NewsletterSubscriber::query()->whereNull('unsubscribed_at');

                        if ($recipientLocale === 'en') {
                            $query->where(function (Builder $q) {
                                $q->whereNull('locale')->orWhere('locale', 'en');
                            });
                        } elseif ($recipientLocale === 'fr') {
                            $query->where('locale', 'fr');
                        }

                        // When limiting, preselect ids so split-by-locale stays within the limit.
                        $limitedIds = null;
                        if (! empty($data['limit'])) {
                            $limit = (int) $data['limit'];
                            if ($limit > 0) {
                                $limitedIds = (clone $query)->limit($limit)->pluck('id')->all();
                                $query = NewsletterSubscriber::query()
                                    ->whereKey($limitedIds)
                                    ->whereNull('unsubscribed_at');
                            }
                        }

                        $count = $limitedIds !== null ? count($limitedIds) : (clone $query)->count();

                        if ($count === 0) {
                            FilamentNotification::make()
                                ->title('No subscribers found')
                                ->warning()
                                ->send();
                            return;
                        }

                        if (! empty($data['dry_run'])) {
                            if ($translateToFr && $recipientLocale === 'all') {
                                $frCount = (clone $query)->where('locale', 'fr')->count();
                                $nonFrCount = (clone $query)
                                    ->where(function (Builder $q) {
                                        $q->whereNull('locale')->orWhere('locale', '!=', 'fr');
                                    })
                                    ->count();

                                FilamentNotification::make()
                                    ->title("Dry run: {$count} subscribers (EN: {$nonFrCount}, FR: {$frCount})")
                                    ->info()
                                    ->send();
                                return;
                            }

                            FilamentNotification::make()
                                ->title("Dry run: {$count} subscribers")
                                ->info()
                                ->send();
                            return;
                        }

                        $payloadEn = [
                            'subject' => $data['subject'],
                            'body_markdown' => $data['body_markdown'],
                            'action_url' => $data['action_url'] ?? null,
                            'action_label' => $data['action_label'] ?? null,
                        ];

                        if (! $translateToFr || $recipientLocale === 'en') {
                            $campaign = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                $payloadEn,
                                $query,
                                auth()->user(),
                                $sendNow
                            );

                            FilamentNotification::make()
                                ->title("Campaign queued for {$campaign->total_subscribers} subscribers")
                                ->success()
                                ->send();
                            return;
                        }

                        if (empty(config('services.deepseek.key'))) {
                            FilamentNotification::make()
                                ->danger()
                                ->title('DeepSeek not configured')
                                ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                                ->send();
                            return;
                        }

                        $translator = app(ContentTranslationService::class);

                        $translated = null;
                        try {
                            $translated = $translator->translateFields([
                                'subject' => (string) ($payloadEn['subject'] ?? ''),
                                'body_markdown' => (string) ($payloadEn['body_markdown'] ?? ''),
                                'action_label' => (string) ($payloadEn['action_label'] ?? ''),
                            ], 'en', 'fr');
                        } catch (\Throwable $e) {
                            FilamentNotification::make()
                                ->danger()
                                ->title('Translation failed')
                                ->body($e->getMessage())
                                ->send();
                            return;
                        }

                        $payloadFr = [
                            ...$payloadEn,
                            'subject' => $translated['subject'] ?? $payloadEn['subject'],
                            'body_markdown' => $translated['body_markdown'] ?? $payloadEn['body_markdown'],
                            'action_label' => $translated['action_label'] ?? $payloadEn['action_label'],
                        ];

                        if ($recipientLocale === 'fr') {
                            $campaign = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                $payloadFr,
                                $query,
                                auth()->user(),
                                $sendNow
                            );

                            FilamentNotification::make()
                                ->title("French campaign queued for {$campaign->total_subscribers} subscribers")
                                ->success()
                                ->send();
                            return;
                        }

                        $frQuery = (clone $query)->where('locale', 'fr');
                        $nonFrQuery = (clone $query)->where(function (Builder $q) {
                            $q->whereNull('locale')->orWhere('locale', '!=', 'fr');
                        });

                        $frCount = (clone $frQuery)->count();
                        $nonFrCount = (clone $nonFrQuery)->count();

                        $campaignEn = null;
                        $campaignFr = null;

                        if ($nonFrCount > 0) {
                            $campaignEn = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                $payloadEn,
                                $nonFrQuery,
                                auth()->user(),
                                $sendNow
                            );
                        }

                        if ($frCount > 0) {
                            $campaignFr = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                $payloadFr,
                                $frQuery,
                                auth()->user(),
                                $sendNow
                            );
                        }

                        if ($campaignEn && $campaignFr) {
                            FilamentNotification::make()
                                ->title("Campaigns queued (EN: {$campaignEn->total_subscribers}, FR: {$campaignFr->total_subscribers})")
                                ->success()
                                ->send();
                            return;
                        }

                        $only = $campaignFr ?: $campaignEn;

                        FilamentNotification::make()
                            ->title("Campaign queued for {$only?->total_subscribers} subscribers")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                ActionsBulkActionGroup::make([
                    ActionsDeleteBulkAction::make(),
                    ActionsBulkAction::make('send_selected')
                        ->label('Send campaign to selected')
                        ->icon('heroicon-o-paper-airplane')
                        ->form([
                            Forms\Components\TextInput::make('subject')
                                ->required()
                                ->maxLength(200)
                                ->default('Latest offers from ' . config('app.name'))
                                ->live(),
                            Forms\Components\MarkdownEditor::make('body_markdown')
                                ->required()
                                ->toolbarButtons([
                                    'bold', 'italic', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'table',
                                ])
                                ->helperText('Markdown supported. Use {{product:ID}} or {{category:ID}} to embed items. Images: ![](https://url).')
                                ->columnSpanFull()
                                ->default('New promotions are live now. Shop the latest deals and limited-time offers.')
                                ->live(),
                            Forms\Components\TextInput::make('action_url')
                                ->label('Action URL')
                                ->url()
                                ->default(rtrim((string) config('app.url'), '/') . '/promotions')
                                ->live(),
                            Forms\Components\TextInput::make('action_label')
                                ->label('Action label')
                                ->maxLength(80)
                                ->default('Shop promotions')
                                ->live(),
                            Forms\Components\Select::make('recipient_locale')
                                ->label('Recipients')
                                ->options([
                                    'all' => 'All selected subscribers',
                                    'en' => 'English + unknown locale',
                                    'fr' => 'French only',
                                ])
                                ->native(false)
                                ->default('all'),
                            Forms\Components\Toggle::make('translate_to_fr')
                                ->label('Translate EN → FR for French recipients')
                                ->helperText('If Recipients = All, sends EN to non-FR and FR to FR (two campaigns).')
                                ->default(false)
                                ->visible(fn (callable $get) => (string) ($get('recipient_locale') ?? 'all') !== 'en'),
                            Forms\Components\Toggle::make('dry_run')
                                ->label('Dry run')
                                ->default(false),
                            Forms\Components\Toggle::make('send_now')
                                ->label('Send immediately (no queue)')
                                ->helperText('Enable to send right away without a queue worker.')
                                ->default(true),
                            Section::make('Preview')
                                ->schema([
                                    Forms\Components\ViewField::make('preview')
                                        ->view('filament.newsletter.preview')
                                        ->live()
                                        ->viewData(fn (SchemaGet $get) => [
                                            'preview_html' => app(NewsletterCampaignRenderer::class)->renderPreviewHtml(
                                                (string) ($get('subject') ?? 'Newsletter preview'),
                                                (string) ($get('body_markdown') ?? ''),
                                                $get('action_url'),
                                                $get('action_label')
                                            ),
                                        ])
                                        ->columnSpanFull(),
                                ])
                                ->collapsed()
                                ->collapsible(),
                        ])
                        ->action(function ($records, array $data): void {
                            $records = $records->whereNull('unsubscribed_at');
                            $count = $records->count();

                            if ($count === 0) {
                                FilamentNotification::make()
                                    ->title('No subscribers selected')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            if (! empty($data['dry_run'])) {
                                if (! empty($data['translate_to_fr']) && (($data['recipient_locale'] ?? 'all') === 'all')) {
                                    $frCount = $records->where('locale', 'fr')->count();
                                    $nonFrCount = $records->where('locale', '!=', 'fr')->count();

                                    FilamentNotification::make()
                                        ->title("Dry run: {$count} subscribers (EN: {$nonFrCount}, FR: {$frCount})")
                                        ->info()
                                        ->send();
                                    return;
                                }

                                FilamentNotification::make()
                                    ->title("Dry run: {$count} subscribers")
                                    ->info()
                                    ->send();
                                return;
                            }

                            $recipientLocale = is_string($data['recipient_locale'] ?? null) ? (string) $data['recipient_locale'] : 'all';
                            $translateToFr = (bool) ($data['translate_to_fr'] ?? false);
                            $sendNow = (bool) ($data['send_now'] ?? true);

                            $ids = $records->pluck('id')->all();

                            $baseQuery = NewsletterSubscriber::query()
                                ->whereKey($ids)
                                ->whereNull('unsubscribed_at');

                            if ($recipientLocale === 'en') {
                                $baseQuery->where(function (Builder $q) {
                                    $q->whereNull('locale')->orWhere('locale', 'en');
                                });
                            } elseif ($recipientLocale === 'fr') {
                                $baseQuery->where('locale', 'fr');
                            }

                            $payloadEn = [
                                'subject' => $data['subject'],
                                'body_markdown' => $data['body_markdown'],
                                'action_url' => $data['action_url'] ?? null,
                                'action_label' => $data['action_label'] ?? null,
                            ];

                            if (! $translateToFr || $recipientLocale === 'en') {
                                $campaign = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                    $payloadEn,
                                    $baseQuery,
                                    auth()->user(),
                                    $sendNow
                                );

                                FilamentNotification::make()
                                    ->title("Campaign queued for {$campaign->total_subscribers} subscribers")
                                    ->success()
                                    ->send();
                                return;
                            }

                            if (empty(config('services.deepseek.key'))) {
                                FilamentNotification::make()
                                    ->danger()
                                    ->title('DeepSeek not configured')
                                    ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                                    ->send();
                                return;
                            }

                            try {
                                $translated = app(ContentTranslationService::class)->translateFields([
                                    'subject' => (string) ($payloadEn['subject'] ?? ''),
                                    'body_markdown' => (string) ($payloadEn['body_markdown'] ?? ''),
                                    'action_label' => (string) ($payloadEn['action_label'] ?? ''),
                                ], 'en', 'fr');
                            } catch (\Throwable $e) {
                                FilamentNotification::make()
                                    ->danger()
                                    ->title('Translation failed')
                                    ->body($e->getMessage())
                                    ->send();
                                return;
                            }

                            $payloadFr = [
                                ...$payloadEn,
                                'subject' => $translated['subject'] ?? $payloadEn['subject'],
                                'body_markdown' => $translated['body_markdown'] ?? $payloadEn['body_markdown'],
                                'action_label' => $translated['action_label'] ?? $payloadEn['action_label'],
                            ];

                            if ($recipientLocale === 'fr') {
                                $campaign = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                    $payloadFr,
                                    $baseQuery,
                                    auth()->user(),
                                    $sendNow
                                );

                                FilamentNotification::make()
                                    ->title("French campaign queued for {$campaign->total_subscribers} subscribers")
                                    ->success()
                                    ->send();
                                return;
                            }

                            $frQuery = (clone $baseQuery)->where('locale', 'fr');
                            $nonFrQuery = (clone $baseQuery)->where(function (Builder $q) {
                                $q->whereNull('locale')->orWhere('locale', '!=', 'fr');
                            });

                            $campaignEn = null;
                            $campaignFr = null;

                            if ((clone $nonFrQuery)->count() > 0) {
                                $campaignEn = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                    $payloadEn,
                                    $nonFrQuery,
                                    auth()->user(),
                                    $sendNow
                                );
                            }

                            if ((clone $frQuery)->count() > 0) {
                                $campaignFr = app(NewsletterCampaignService::class)->createAndQueueCampaign(
                                    $payloadFr,
                                    $frQuery,
                                    auth()->user(),
                                    $sendNow
                                );
                            }

                            if ($campaignEn && $campaignFr) {
                                FilamentNotification::make()
                                    ->title("Campaigns queued (EN: {$campaignEn->total_subscribers}, FR: {$campaignFr->total_subscribers})")
                                    ->success()
                                    ->send();
                                return;
                            }

                            $only = $campaignFr ?: $campaignEn;
                                
                            FilamentNotification::make()
                                ->title("Campaign queued for {$only?->total_subscribers} subscribers")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(50);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
            'view' => Pages\ViewNewsletterSubscriber::route('/{record}'),
        ];
    }
}
