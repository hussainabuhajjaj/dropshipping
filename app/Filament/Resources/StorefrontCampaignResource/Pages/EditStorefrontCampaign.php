<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCampaignResource\Pages;

use App\Filament\Resources\StorefrontCampaignResource;
use App\Models\MarketingContentDraft;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontCampaign extends EditRecord
{
    protected static string $resource = StorefrontCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('translate_to_fr')
                ->label('Translate EN → FR')
                ->icon('heroicon-o-language')
                ->requiresConfirmation()
                ->schema([
                    Forms\Components\Toggle::make('overwrite')
                        ->label('Overwrite existing FR override')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    if (empty(config('services.deepseek.key'))) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek not configured')
                            ->body('Set DEEPSEEK_API_KEY in your .env to enable translations.')
                            ->send();
                        return;
                    }

                    $overwrite = (bool) ($data['overwrite'] ?? false);
                    $state = $this->form->getState();

                    try {
                        $service = app(ContentTranslationService::class);
                        $translated = $service->translateFields([
                            'name' => (string) ($state['name'] ?? ''),
                            'hero_kicker' => (string) ($state['hero_kicker'] ?? ''),
                            'hero_subtitle' => (string) ($state['hero_subtitle'] ?? ''),
                            'content' => (string) ($state['content'] ?? ''),
                        ], 'en', 'fr');
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation failed')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }

                    $overrides = $state['locale_overrides'] ?? [];
                    $overrides = is_array($overrides) ? $overrides : [];

                    $index = null;
                    foreach ($overrides as $i => $override) {
                        if (is_array($override) && (($override['locale'] ?? null) === 'fr')) {
                            $index = $i;
                            break;
                        }
                    }

                    $fr = $index !== null && is_array($overrides[$index] ?? null) ? $overrides[$index] : [];
                    $fr['locale'] = 'fr';

                    foreach ($translated as $key => $value) {
                        if (! is_string($key) || ! is_string($value)) {
                            continue;
                        }

                        $existingValue = $fr[$key] ?? null;
                        $hasExisting = is_string($existingValue) && trim($existingValue) !== '';

                        if ($hasExisting && ! $overwrite) {
                            continue;
                        }

                        if (trim($value) !== '') {
                            $fr[$key] = $value;
                        }
                    }

                    if ($index === null) {
                        $overrides[] = $fr;
                    } else {
                        $overrides[$index] = $fr;
                    }

                    $state['locale_overrides'] = array_values($overrides);
                    $this->form->fill($state);

                    Notification::make()
                        ->success()
                        ->title('French override updated')
                        ->body('Review the Schedule & locale section, then save.')
                        ->send();
                }),
            Actions\Action::make('generate_ai')
                ->label('Generate with AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    if (empty(config('services.deepseek.key'))) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek not configured')
                            ->body('Set DEEPSEEK_API_KEY in your .env to enable AI generation.')
                            ->send();
                        return;
                    }

                    $state = $this->form->getState();
                    $fields = [
                        'name' => (string) ($state['name'] ?? ''),
                        'hero_kicker' => (string) ($state['hero_kicker'] ?? ''),
                        'hero_subtitle' => (string) ($state['hero_subtitle'] ?? ''),
                        'content' => (string) ($state['content'] ?? ''),
                    ];

                    $promptContext = [
                        'type' => $state['type'] ?? null,
                        'placements' => $state['placements'] ?? [],
                        'theme' => $state['theme'] ?? [],
                        'locale' => 'fr',
                    ];

                    MarketingContentDraft::create([
                        'target_type' => 'campaign',
                        'target_id' => $this->record->id,
                        'locale' => 'fr',
                        'channel' => 'web',
                        'generated_fields' => $fields,
                        'prompt_context' => $promptContext,
                        'status' => 'pending_review',
                        'requested_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('AI draft created')
                        ->body('Review and approve in Marketing Content Drafts.')
                        ->send();
                }),
            Actions\Action::make('submit_for_approval')
                ->label('Submit for approval')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'rejected'], true))
                ->action(function (): void {
                    $this->record->update(['status' => 'pending_approval', 'is_active' => false]);
                    Notification::make()->title('Campaign submitted')->success()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, ['pending_approval'], true))
                ->action(function (): void {
                    $this->record->update(['status' => 'approved', 'is_active' => false]);
                    Notification::make()->title('Campaign approved')->success()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, ['pending_approval'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'rejected', 'is_active' => false]);
                    Notification::make()->title('Campaign rejected').danger()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            Actions\Action::make('activate')
                ->label('Activate')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, ['approved', 'scheduled'], true))
                ->action(function (): void {
                    $payload = ['status' => 'active', 'is_active' => true];
                    if (! $this->record->starts_at) {
                        $payload['starts_at'] = now();
                    }
                    $this->record->update($payload);
                    Notification::make()->title('Campaign activated')->success()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            Actions\Action::make('end_campaign')
                ->label('End')
                ->color('gray')
                ->visible(fn (): bool => in_array($this->record->status, ['active', 'approved', 'scheduled'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'ended',
                        'is_active' => false,
                        'ends_at' => $this->record->ends_at ?? now(),
                    ]);
                    Notification::make()->title('Campaign ended')->success()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
