<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportConversationResource\Pages;

use App\Filament\Resources\SupportConversationResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportConversations extends ListRecords
{
    protected static string $resource = SupportConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        $statsWidget = \App\Filament\Resources\SupportConversationResource\Widgets\SupportConversationStatsWidget::class;
        if (class_exists($statsWidget)) {
            $widgets[] = $statsWidget;
        }

        return $widgets;
    }
}
