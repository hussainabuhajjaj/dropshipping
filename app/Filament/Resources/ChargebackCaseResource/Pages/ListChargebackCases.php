<?php

namespace App\Filament\Resources\ChargebackCaseResource\Pages;

use App\Filament\Resources\ChargebackCaseResource;
use Filament\Resources\Pages\ListRecords;

class ListChargebackCases extends ListRecords
{
    protected static string $resource = ChargebackCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
