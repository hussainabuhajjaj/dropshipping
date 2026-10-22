<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileTranslationResource\Pages;

use App\Filament\Resources\MobileTranslationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListMobileTranslations extends ListRecords
{
    protected static string $resource = MobileTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
