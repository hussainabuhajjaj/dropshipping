<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileReleaseResource\Pages;

use App\Filament\Resources\MobileReleaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMobileRelease extends EditRecord
{
    protected static string $resource = MobileReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
