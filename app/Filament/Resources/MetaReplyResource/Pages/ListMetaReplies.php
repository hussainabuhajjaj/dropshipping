<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaReplyResource\Pages;

use App\Filament\Resources\MetaReplyResource;
use Filament\Resources\Pages\ListRecords;

class ListMetaReplies extends ListRecords
{
    protected static string $resource = MetaReplyResource::class;
}
