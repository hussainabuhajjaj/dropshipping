<?php

declare(strict_types=1);

namespace App\Filament\Resources\QrCampaignResource\Pages;

use App\Filament\Resources\QrCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQrCampaign extends CreateRecord
{
    protected static string $resource = QrCampaignResource::class;
}
