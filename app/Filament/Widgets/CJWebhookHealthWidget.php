<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\CJWebhookLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CJWebhookHealthWidget extends BaseWidget
{
    protected ?string $heading = 'CJ Webhook Health';
    protected ?string $description = 'Recent CJ webhook throughput and activity.';

    protected function getStats(): array
    {
        $since = now()->subDay();
        $total = CJWebhookLog::query()->where('created_at', '>=', $since)->count();
        $last = CJWebhookLog::query()->latest('created_at')->first();
        $lastAt = $last?->created_at?->diffForHumans() ?? 'Never';

        return [
            Stat::make('Last 24h', (string) $total)->color($total === 0 ? 'warning' : 'success'),
            Stat::make('Last received', $lastAt)->color($last ? 'primary' : 'gray'),
            Stat::make('Message types', (string) CJWebhookLog::query()->distinct('message_type')->count('message_type'))
                ->color('gray'),
        ];
    }
}
