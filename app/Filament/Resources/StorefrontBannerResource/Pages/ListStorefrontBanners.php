<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontBannerResource\Pages;

use App\Filament\Resources\StorefrontBannerResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListStorefrontBanners extends ListRecords
{
    protected static string $resource = StorefrontBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
