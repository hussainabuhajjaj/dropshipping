<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerSegmentResource\Pages;

use App\Filament\Resources\CustomerSegmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerSegment extends CreateRecord
{
    protected static string $resource = CustomerSegmentResource::class;
}
