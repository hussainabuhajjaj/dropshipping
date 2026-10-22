<?php

declare(strict_types=1);

namespace App\Filament\Resources\CampaignParticipationResource\Pages;

use App\Filament\Resources\CampaignParticipationResource;
use Filament\Resources\Pages\EditRecord;

class EditCampaignParticipation extends EditRecord
{
    protected static string $resource = CampaignParticipationResource::class;
}
