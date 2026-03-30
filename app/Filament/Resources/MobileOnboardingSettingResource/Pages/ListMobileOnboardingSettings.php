<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileOnboardingSettingResource\Pages;

use App\Filament\Resources\MobileOnboardingSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobileOnboardingSettings extends ListRecords
{
    protected static string $resource = MobileOnboardingSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
