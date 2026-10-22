<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomePageSettingResource\Pages;

use App\Filament\Resources\HomePageSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomePageSettings extends ListRecords
{
    protected static string $resource = HomePageSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
