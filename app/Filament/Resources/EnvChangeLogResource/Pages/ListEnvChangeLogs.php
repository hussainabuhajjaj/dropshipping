<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnvChangeLogResource\Pages;

use App\Filament\Resources\EnvChangeLogResource;
use Filament\Resources\Pages\ListRecords;

class ListEnvChangeLogs extends ListRecords
{
    protected static string $resource = EnvChangeLogResource::class;
}
