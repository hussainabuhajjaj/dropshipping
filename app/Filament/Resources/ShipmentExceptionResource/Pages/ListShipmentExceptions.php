<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentExceptionResource\Pages;

use App\Filament\Resources\ShipmentExceptionResource;
use Filament\Resources\Pages\ListRecords;

class ListShipmentExceptions extends ListRecords
{
    protected static string $resource = ShipmentExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
