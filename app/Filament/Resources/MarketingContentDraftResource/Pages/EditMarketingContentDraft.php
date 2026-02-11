<?php

declare(strict_types=1);

namespace App\Filament\Resources\MarketingContentDraftResource\Pages;

use App\Filament\Resources\MarketingContentDraftResource;
use App\Models\MarketingContentDraft;
use App\Models\StorefrontCampaign;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Filament\Notifications\Notification;

class EditMarketingContentDraft extends EditRecord
{
    protected static string $resource = MarketingContentDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve_apply')
                ->label('Approve & Apply')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'pending_review')
                ->action(function (): void {
                    /** @var MarketingContentDraft $draft */
                    $draft = $this->record;
                    $applied = $this->applyDraft($draft);
                    $draft->update([
                        'status' => $applied ? 'approved' : 'rejected',
                        'approved_by' => auth()->id(),
                        'rejected_reason' => $applied ? null : ($draft->rejected_reason ?: 'Apply failed'),
                    ]);
                    Notification::make()
                        ->title($applied ? 'Draft approved and applied' : 'Draft could not be applied')
                        ->{$applied ? 'success' : 'danger'}()
                        ->send();
                    $this->redirect($this->getRedirectUrl());
                }),
        ];
    }

    private function applyDraft(MarketingContentDraft $draft): bool
    {
        $fields = $draft->generated_fields ?? [];
        $locale = $draft->locale;

        $mergeLocaleOverride = function ($model, array $map) use ($fields, $locale): bool {
            if (! $model) {
                return false;
            }
            $overrides = $model->locale_overrides ?? [];
            $overrides = is_array($overrides) ? $overrides : [];

            $found = false;
            foreach ($overrides as &$override) {
                if (is_array($override) && ($override['locale'] ?? null) === $locale) {
                    foreach ($map as $outKey => $inKey) {
                        $value = $fields[$inKey] ?? null;
                        if ($value !== null && $value !== '') {
                            $override[$outKey] = $value;
                        }
                    }
                    $found = true;
                    break;
                }
            }
            unset($override);

            if (! $found) {
                $payload = ['locale' => $locale];
                foreach ($map as $outKey => $inKey) {
                    $value = $fields[$inKey] ?? null;
                    if ($value !== null && $value !== '') {
                        $payload[$outKey] = $value;
                    }
                }
                $overrides[] = $payload;
            }

            $model->update(['locale_overrides' => array_values($overrides)]);
            return true;
        };

        if ($draft->target_type === 'campaign' && $draft->target_id) {
            return $mergeLocaleOverride(StorefrontCampaign::find($draft->target_id), [
                'name' => 'title_' . $locale,
                'hero_kicker' => 'hero_kicker_' . $locale,
                'hero_subtitle' => 'hero_subtitle_' . $locale,
                'content' => 'content_' . $locale,
            ]);
        }

        if ($draft->target_type === 'banner' && $draft->target_id) {
            return $mergeLocaleOverride(\App\Models\StorefrontBanner::find($draft->target_id), [
                'title' => 'title_' . $locale,
                'description' => 'content_' . $locale,
                'badge_text' => 'hero_kicker_' . $locale,
                'cta_text' => 'hero_subtitle_' . $locale,
            ]);
        }

        if ($draft->target_type === 'promotion' && $draft->target_id) {
            return $mergeLocaleOverride(\App\Models\Promotion::find($draft->target_id), [
                'name' => 'title_' . $locale,
                'description' => 'content_' . $locale,
            ]);
        }

        if ($draft->target_type === 'coupon' && $draft->target_id) {
            return $mergeLocaleOverride(\App\Models\Coupon::find($draft->target_id), [
                'description' => 'content_' . $locale,
            ]);
        }

        return false;
    }
}
