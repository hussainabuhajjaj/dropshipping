<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReturnRequestResource\Pages;

use App\Filament\Resources\ReturnRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListReturnRequests extends ListRecords
{
    protected static string $resource = ReturnRequestResource::class;
}
