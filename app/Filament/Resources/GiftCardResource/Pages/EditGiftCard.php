<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCardResource\Pages;

use App\Filament\Resources\GiftCardResource;
use Filament\Resources\Pages\EditRecord;

class EditGiftCard extends EditRecord
{
    protected static string $resource = GiftCardResource::class;
}
