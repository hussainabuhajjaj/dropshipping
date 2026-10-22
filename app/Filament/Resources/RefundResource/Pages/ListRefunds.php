<?php

declare(strict_types=1);

namespace App\Filament\Resources\RefundResource\Pages;

use App\Filament\Resources\RefundResource;
use Filament\Resources\Pages\ListRecords;

class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;
}
