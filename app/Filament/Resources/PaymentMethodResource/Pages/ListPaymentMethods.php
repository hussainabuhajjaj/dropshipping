<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethodResource\Pages;

use App\Filament\Resources\PaymentMethodResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;
}
