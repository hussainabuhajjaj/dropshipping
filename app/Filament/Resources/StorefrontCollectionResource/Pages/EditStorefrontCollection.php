<?php

declare(strict_types=1);

namespace App\Filament\Resources\StorefrontCollectionResource\Pages;

use App\Filament\Resources\StorefrontCollectionResource;
use Filament\Resources\Pages\EditRecord;

class EditStorefrontCollection extends EditRecord
{
    protected static string $resource = StorefrontCollectionResource::class;
}
