<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCardResource\Pages;

use App\Filament\Resources\GiftCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCard extends CreateRecord
{
    protected static string $resource = GiftCardResource::class;
}
