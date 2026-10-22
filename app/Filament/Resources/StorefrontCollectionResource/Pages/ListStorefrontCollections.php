<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCollectionResource\Pages;

use App\Filament\Resources\StorefrontCollectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStorefrontCollections extends ListRecords
{
    protected static string $resource = StorefrontCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
