<?php

declare(strict_types=1);

namespace App\Filament\Resources\LinehaulShipmentResource\Pages;

use App\Filament\Resources\LinehaulShipmentResource;
use Filament\Resources\Pages\ListRecords;

class ListLinehaulShipments extends ListRecords
{
    protected static string $resource = LinehaulShipmentResource::class;
}
