<?php

namespace App\Filament\Resources\MessageLogResource\Pages;

use App\Filament\Resources\MessageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageLogs extends ListRecords
{
    protected static string $resource = MessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
