<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierProductResource\Pages;

use App\Filament\Resources\SupplierProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierProduct extends CreateRecord
{
    protected static string $resource = SupplierProductResource::class;
}
