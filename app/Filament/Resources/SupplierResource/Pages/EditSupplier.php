<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;
}
