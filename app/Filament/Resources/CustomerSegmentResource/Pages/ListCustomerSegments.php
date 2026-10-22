<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerSegmentResource\Pages;

use App\Filament\Resources\CustomerSegmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerSegments extends ListRecords
{
    protected static string $resource = CustomerSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
