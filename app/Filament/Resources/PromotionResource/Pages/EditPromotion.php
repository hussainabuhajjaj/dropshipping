<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

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
                            'description' => (string) ($state['description'] ?? ''),
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
                        ->body('Review the Locale overrides section, then save.')
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
