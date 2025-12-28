<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontSettingResource\Pages;

use App\Filament\Resources\StorefrontSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStorefrontSetting extends CreateRecord
{
    protected static string $resource = StorefrontSettingResource::class;
}
