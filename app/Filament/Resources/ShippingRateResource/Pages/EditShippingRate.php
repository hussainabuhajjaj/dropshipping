<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingRateResource\Pages;

use App\Filament\Resources\ShippingRateResource;
use Filament\Resources\Pages\EditRecord;

class EditShippingRate extends EditRecord
{
    protected static string $resource = ShippingRateResource::class;
}
