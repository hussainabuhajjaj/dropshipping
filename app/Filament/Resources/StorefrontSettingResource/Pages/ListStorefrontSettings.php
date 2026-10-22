<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontSettingResource\Pages;

use App\Filament\Resources\StorefrontSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontSettings extends ListRecords
{
    protected static string $resource = StorefrontSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
