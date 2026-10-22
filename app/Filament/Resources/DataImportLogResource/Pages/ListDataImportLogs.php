<?php

declare(strict_types=1);

namespace App\Filament\Resources\DataImportLogResource\Pages;

use App\Filament\Resources\DataImportLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDataImportLogs extends ListRecords
{
    protected static string $resource = DataImportLogResource::class;
}
