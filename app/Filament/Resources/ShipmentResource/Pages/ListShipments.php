<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Resources\Pages\ListRecords;

class ListShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;
}
