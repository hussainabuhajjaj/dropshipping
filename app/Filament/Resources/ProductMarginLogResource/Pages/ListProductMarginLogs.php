<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductMarginLogResource\Pages;

use App\Filament\Resources\ProductMarginLogResource;
use Filament\Resources\Pages\ListRecords;

class ListProductMarginLogs extends ListRecords
{
    protected static string $resource = ProductMarginLogResource::class;
}
