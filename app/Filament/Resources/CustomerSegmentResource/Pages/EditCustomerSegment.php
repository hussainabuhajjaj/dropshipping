<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerSegmentResource\Pages;

use App\Filament\Resources\CustomerSegmentResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSegment extends EditRecord
{
    protected static string $resource = CustomerSegmentResource::class;
}
