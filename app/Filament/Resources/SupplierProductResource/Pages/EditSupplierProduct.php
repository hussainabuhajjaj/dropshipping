<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierProductResource\Pages;

use App\Filament\Resources\SupplierProductResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplierProduct extends EditRecord
{
    protected static string $resource = SupplierProductResource::class;
}
