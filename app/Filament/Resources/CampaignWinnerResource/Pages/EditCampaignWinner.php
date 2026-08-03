<?php

declare(strict_types=1);

namespace App\Filament\Resources\CampaignWinnerResource\Pages;

use App\Filament\Resources\CampaignWinnerResource;
use Filament\Resources\Pages\EditRecord;

class EditCampaignWinner extends EditRecord
{
    protected static string $resource = CampaignWinnerResource::class;
}
