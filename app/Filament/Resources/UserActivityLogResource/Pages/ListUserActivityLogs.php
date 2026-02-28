<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserActivityLogResource\Pages;

use App\Filament\Resources\UserActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListUserActivityLogs extends ListRecords
{
    protected static string $resource = UserActivityLogResource::class;
}
