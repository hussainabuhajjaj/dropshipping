<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;
}
