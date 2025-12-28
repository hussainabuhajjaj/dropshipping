<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontBannerResource\Pages;

use App\Filament\Resources\StorefrontBannerResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditStorefrontBanner extends EditRecord
{
    protected static string $resource = StorefrontBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
