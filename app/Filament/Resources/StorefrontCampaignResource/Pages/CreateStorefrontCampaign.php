<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCampaignResource\Pages;

use App\Filament\Resources\StorefrontCampaignResource;
use App\Models\CampaignProductQuery;
use App\Models\StorefrontCampaign;
use App\Services\AI\ContentTranslationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStorefrontCampaign extends CreateRecord
{
    protected static string $resource = StorefrontCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->productQueryData = $data['productQuery'] ?? [];
        unset($data['productQuery']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->saveProductQuery($this->record);
    }

    private array $productQueryData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('translate_to_fr')
                ->label('Translate EN → FR')
                ->icon('heroicon-o-language')
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
        ];
    }

    private function saveProductQuery(StorefrontCampaign $campaign): void
    {
        $data = $this->productQueryData;
        if (empty($data)) {
            return;
        }

        $query = $campaign->productQuery ?? new CampaignProductQuery(['storefront_campaign_id' => $campaign->id]);
        $query->fill([
            'keywords' => $data['keywords'] ?? null,
            'cj_category_id' => $data['cj_category_id'] ?? null,
            'min_price' => $data['min_price'] ?? null,
            'max_price' => $data['max_price'] ?? null,
            'max_products' => $data['max_products'] ?? 50,
            'margin_percent' => $data['margin_percent'] ?? 60,
            'auto_activate' => $data['auto_activate'] ?? true,
            'sort_by' => $data['sort_by'] ?? null,
            'status' => 'pending',
        ]);
        $query->save();
    }
}
