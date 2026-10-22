<?php

declare(strict_types=1);

namespace App\Filament\Resources\FulfillmentJobResource\Pages;

use App\Filament\Resources\FulfillmentJobResource;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentJobs extends ListRecords
{
    protected static string $resource = FulfillmentJobResource::class;
}
