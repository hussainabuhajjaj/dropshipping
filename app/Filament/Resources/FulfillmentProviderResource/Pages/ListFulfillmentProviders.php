<?php

declare(strict_types=1);

namespace App\Filament\Resources\FulfillmentProviderResource\Pages;

use App\Filament\Resources\FulfillmentProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentProviders extends ListRecords
{
    protected static string $resource = FulfillmentProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
