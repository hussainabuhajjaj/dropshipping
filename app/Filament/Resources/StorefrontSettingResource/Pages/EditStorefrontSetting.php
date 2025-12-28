<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontSettingResource\Pages;

use App\Filament\Resources\StorefrontSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontSetting extends EditRecord
{
    protected static string $resource = StorefrontSettingResource::class;
}
